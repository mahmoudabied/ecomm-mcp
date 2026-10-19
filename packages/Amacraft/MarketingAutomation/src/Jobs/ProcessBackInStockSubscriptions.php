<?php

namespace Amacraft\MarketingAutomation\Jobs;

use Amacraft\MarketingAutomation\DTOs\TriggerEvent;
use Amacraft\MarketingAutomation\Engine\EnrolmentService;
use Amacraft\MarketingAutomation\Enums\FlowStatus;
use Amacraft\MarketingAutomation\Enums\TriggerType;
use Amacraft\MarketingAutomation\Models\BackInStockSubscription;
use Amacraft\MarketingAutomation\Models\Flow;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

class ProcessBackInStockSubscriptions implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $productId,
    ) {
        $this->onQueue(config('marketing-automation.queues.automation'));
    }

    /**
     * Enrol the waiting subscribers, marking only the ones that entered a flow.
     */
    public function handle(EnrolmentService $enrolmentService): void
    {
        $hasActiveFlow = Flow::query()
            ->where('trigger_type', TriggerType::BackInStock->value)
            ->where('status', FlowStatus::Active->value)
            ->whereNotNull('activated_at')
            ->exists();

        if (! $hasActiveFlow) {
            return;
        }

        BackInStockSubscription::query()
            ->where('product_id', $this->productId)
            ->where('status', 'waiting')
            ->with('contact')
            ->chunkById(500, function ($subscriptions) use ($enrolmentService) {
                foreach ($subscriptions as $subscription) {
                    $this->notify($enrolmentService, $subscription);
                }
            });
    }

    /**
     * Enrol one subscriber; the subscription is marked notified on success only.
     */
    protected function notify(EnrolmentService $enrolmentService, BackInStockSubscription $subscription): void
    {
        $contact = $subscription->contact;

        if ($contact === null) {
            return;
        }

        $enrolments = $enrolmentService->enrol(new TriggerEvent(
            type: TriggerType::BackInStock,
            channelId: $subscription->channel_id,
            customerId: $contact->customer_id,
            email: $contact->email,
            phone: $contact->phone_e164,
            triggerKey: "bis:{$subscription->id}",
            payload: ['product_id' => $subscription->product_id],
            locale: $contact->locale,
        ));

        if ($enrolments !== []) {
            $subscription->update(['status' => 'notified', 'notified_at' => now()]);
        }
    }
}
