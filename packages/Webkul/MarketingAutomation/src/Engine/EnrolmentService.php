<?php

namespace Webkul\MarketingAutomation\Engine;

use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Webkul\MarketingAutomation\DTOs\TriggerEvent;
use Webkul\MarketingAutomation\Enums\EnrolmentStatus;
use Webkul\MarketingAutomation\Enums\FlowStatus;
use Webkul\MarketingAutomation\Enums\JourneyEvent;
use Webkul\MarketingAutomation\Enums\RoutingTarget;
use Webkul\MarketingAutomation\Enums\TriggerType;
use Webkul\MarketingAutomation\Jobs\ProcessEnrolmentStep;
use Webkul\MarketingAutomation\Models\Contact;
use Webkul\MarketingAutomation\Models\Enrolment;
use Webkul\MarketingAutomation\Models\Flow;
use Webkul\MarketingAutomation\Services\ContactResolver;
use Webkul\MarketingAutomation\Services\Settings;
use Webkul\MarketingAutomation\Services\StatsRecorder;

class EnrolmentService
{
    /**
     * Create a new enrolment service instance.
     */
    public function __construct(
        protected ContactResolver $contactResolver,
        protected Settings $settings,
        protected StatsRecorder $statsRecorder,
    ) {}

    /**
     * Enrol a trigger event into every matching active flow.
     *
     * @return array<int, Enrolment>
     */
    public function enrol(TriggerEvent $event): array
    {
        $enrolments = [];

        $channelCode = DB::table('channels')->where('id', $event->channelId)->value('code') ?? '';

        $flows = array_filter(
            $this->matchingFlows($event),
            fn (Flow $flow) => $this->triggerConfigMatches($flow, $event) && ! $this->routingDisabled($flow, $channelCode),
        );

        // Contacts are created lazily: an event no flow wants leaves no trace.
        if ($flows === []) {
            return [];
        }

        $contact = $this->contactResolver->resolve(
            channelId: $event->channelId,
            customerId: $event->customerId,
            email: $event->email,
            phone: $event->phone,
            locale: $event->locale,
        );

        foreach ($flows as $flow) {
            $enrolment = $this->createEnrolment($flow, $contact, $event);

            if ($enrolment === null) {
                continue;
            }

            $enrolments[] = $enrolment;

            Event::dispatch('marketing_automation.enrolment.create.after', [$enrolment]);
        }

        return $enrolments;
    }

    /**
     * Active flows for this trigger attached to the event's sales channel.
     *
     * @return array<int, Flow>
     */
    protected function matchingFlows(TriggerEvent $event): array
    {
        return Flow::query()
            ->with('currentVersion')
            ->where('trigger_type', $event->type->value)
            ->where('status', FlowStatus::Active->value)
            ->whereNotNull('activated_at')
            ->where('activated_at', '<=', now())
            ->whereHas('channels', fn ($query) => $query->where('channels.id', $event->channelId))
            ->get()
            ->all();
    }

    /**
     * Extra per-flow trigger configuration checks.
     */
    protected function triggerConfigMatches(Flow $flow, TriggerEvent $event): bool
    {
        $config = $flow->trigger_config ?? [];

        if ($flow->trigger_type === TriggerType::OrderStatusChanged && isset($config['status'])) {
            return ($event->payload['status'] ?? null) === $config['status'];
        }

        if ($flow->trigger_type === TriggerType::CustomerInactive && isset($config['inactive_days'])) {
            return (int) ($event->payload['inactive_days'] ?? -1) === (int) $config['inactive_days'];
        }

        if ($flow->trigger_type === TriggerType::CartAbandoned && isset($config['abandon_delay_minutes'])) {
            $idleMinutes = $event->payload['idle_minutes'] ?? null;

            if ($idleMinutes === null || (float) $idleMinutes < (float) $config['abandon_delay_minutes']) {
                return false;
            }
        }

        return true;
    }

    /**
     * Native routing only; template_key-less flows always run natively.
     */
    protected function routingDisabled(Flow $flow, string $channelCode): bool
    {
        if ($flow->template_key === null) {
            return false;
        }

        return $this->settings->routing($channelCode, $flow->template_key) !== RoutingTarget::Native;
    }

    /**
     * Create the enrolment, losing cleanly when another worker won the race.
     * The re-entry check and the insert run atomically against the flow's rows.
     */
    protected function createEnrolment(Flow $flow, Contact $contact, TriggerEvent $event): ?Enrolment
    {
        $definition = new FlowDefinition($flow->currentVersion->definition);

        $token = (string) Str::ulid();

        try {
            $enrolment = DB::transaction(function () use ($flow, $contact, $event, $definition, $token) {
                $reentryOpen = $flow->enrolments()
                    ->where('contact_id', $contact->id)
                    ->where('created_at', '>=', now()->subHours($flow->reentry_window_hours))
                    ->lockForUpdate()
                    ->exists();

                if ($reentryOpen) {
                    return null;
                }

                return $flow->enrolments()->create([
                    'flow_version_id'  => $flow->current_version_id,
                    'contact_id'       => $contact->id,
                    'channel_id'       => $event->channelId,
                    'trigger_key'      => substr($event->triggerKey, 0, 100),
                    'trigger_payload'  => $event->payload,
                    'status'           => EnrolmentStatus::Active->value,
                    'current_step_key' => $definition->entry(),
                    'next_run_at'      => now(),
                    'lock_token'       => $token,
                    'locked_until'     => now()->addMinutes(5),
                ]);
            });
        } catch (UniqueConstraintViolationException) {
            return null;
        }

        if ($enrolment === null) {
            return null;
        }

        $enrolment->events()->create([
            'step_key' => $definition->entry(),
            'event'    => JourneyEvent::Entered->value,
        ]);

        $this->statsRecorder->increment('enrolled', [
            'flow_id'    => $flow->id,
            'step_key'   => $definition->entry(),
            'channel_id' => $event->channelId,
        ]);

        ProcessEnrolmentStep::dispatch($enrolment->id, $token);

        return $enrolment;
    }
}
