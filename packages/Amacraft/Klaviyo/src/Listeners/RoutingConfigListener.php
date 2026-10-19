<?php

namespace Amacraft\Klaviyo\Listeners;

use Illuminate\Support\Facades\Event;
use Amacraft\MarketingAutomation\Enums\EnrolmentStatus;
use Amacraft\MarketingAutomation\Enums\JourneyEvent;
use Amacraft\MarketingAutomation\Enums\RoutingTarget;
use Amacraft\MarketingAutomation\Models\Enrolment;
use Amacraft\MarketingAutomation\Models\Flow;
use Amacraft\MarketingAutomation\Services\Settings;

class RoutingConfigListener
{
    /**
     * Create a new routing config listener instance.
     */
    public function __construct(
        protected Settings $settings,
    ) {}

    /**
     * Register the listeners for the Klaviyo module.
     */
    public function subscribe($events): void
    {
        $events->listen('core.configuration.save.after', [self::class, 'applyRouting']);
    }

    /**
     * Cancel the native enrolments of flows now routed to Klaviyo or disabled.
     */
    public function applyRouting(): void
    {
        foreach (Flow::query()->whereNotNull('template_key')->where('status', 'active')->get() as $flow) {
            foreach ($flow->channels as $channel) {
                $target = $this->settings->routing($channel->code, (string) $flow->template_key);

                if ($target === RoutingTarget::Native) {
                    continue;
                }

                $this->cancelEnrolments($flow, (int) $channel->id);
            }
        }
    }

    /**
     * Cancel one flow's active enrolments in one sales channel.
     */
    protected function cancelEnrolments(Flow $flow, int $channelId): void
    {
        $enrolments = Enrolment::query()
            ->where('flow_id', $flow->id)
            ->where('channel_id', $channelId)
            ->where('status', EnrolmentStatus::Active->value)
            ->get();

        foreach ($enrolments as $enrolment) {
            $enrolment->forceFill([
                'status'       => EnrolmentStatus::Cancelled->value,
                'exit_reason'  => 'routing_disabled',
                'completed_at' => now(),
                'next_run_at'  => null,
            ])->save();

            $enrolment->events()->create([
                'step_key' => $enrolment->current_step_key,
                'event'    => JourneyEvent::Exited->value,
                'reason'   => 'routing_disabled',
            ]);

            Event::dispatch('marketing_automation.enrolment.finish.after', [$enrolment]);
        }
    }
}
