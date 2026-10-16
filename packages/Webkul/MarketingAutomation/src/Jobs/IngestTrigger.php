<?php

namespace Webkul\MarketingAutomation\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Spatie\Multitenancy\Jobs\NotTenantAware;
use Webkul\MarketingAutomation\DTOs\TriggerEvent;
use Webkul\MarketingAutomation\Engine\EnrolmentService;
use Webkul\MarketingAutomation\Enums\TriggerType;

class IngestTrigger implements NotTenantAware, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    /**
     * Create a new job instance.
     *
     * @param  array<string, mixed>  $trigger
     */
    public function __construct(
        protected array $trigger,
    ) {
        $this->onQueue('automation');
    }

    /**
     * Serialise a trigger event for the queue.
     *
     * @param  array<string, mixed>  $payload
     */
    public static function fromDto(TriggerEvent $event, array $payload = []): static
    {
        return new static([
            'type'        => $event->type->value,
            'channel_id'  => $event->channelId,
            'customer_id' => $event->customerId,
            'email'       => $event->email,
            'phone'       => $event->phone,
            'trigger_key' => $event->triggerKey,
            'payload'     => $payload ?: $event->payload,
            'locale'      => $event->locale,
        ]);
    }

    /**
     * Queue the ingestion of a trigger event.
     *
     * @param  array<string, mixed>  $payload
     */
    public static function dispatchDto(TriggerEvent $event, array $payload = []): void
    {
        dispatch(self::fromDto($event, $payload));
    }

    /**
     * Rebuild the event and run the enrolment pass.
     */
    public function handle(EnrolmentService $enrolmentService): void
    {
        $trigger = $this->trigger;

        $enrolmentService->enrol(new TriggerEvent(
            type: TriggerType::from($trigger['type']),
            channelId: (int) $trigger['channel_id'],
            customerId: $trigger['customer_id'] !== null ? (int) $trigger['customer_id'] : null,
            email: $trigger['email'],
            phone: $trigger['phone'],
            triggerKey: (string) $trigger['trigger_key'],
            payload: (array) $trigger['payload'],
            locale: $trigger['locale'] ?? null,
        ));
    }
}
