<?php

namespace Amacraft\MarketingAutomation\Listeners;

use Amacraft\MarketingAutomation\DTOs\TriggerEvent;
use Amacraft\MarketingAutomation\Engine\ExitGoalService;
use Amacraft\MarketingAutomation\Enums\TriggerType;
use Amacraft\MarketingAutomation\Jobs\AttributeOrder;
use Amacraft\MarketingAutomation\Jobs\IngestTrigger;
use Webkul\Sales\Models\Order;

class OrderListener
{
    /**
     * Create a new order listener instance.
     */
    public function __construct(
        protected ExitGoalService $exitGoalService,
    ) {}

    /**
     * Register the listeners for the automation module.
     */
    public function subscribe($events): void
    {
        $events->listen('checkout.order.save.after', [self::class, 'placed']);
        $events->listen('sales.order.update-status.after', [self::class, 'updated']);
        $events->listen('sales.order.cancel.after', [self::class, 'updated']);
    }

    /**
     * A new order was placed: settle the exit goals synchronously (SC-005),
     * queue the trigger ingestion and the attribution.
     */
    public function placed(Order $order): void
    {
        $this->exitGoalService->orderPlaced($order);

        IngestTrigger::dispatchDto(new TriggerEvent(
            type: TriggerType::OrderPlaced,
            channelId: (int) $order->channel_id,
            customerId: $order->customer_id,
            email: $order->customer_email,
            phone: $order->billing_address?->phone,
            triggerKey: "order:{$order->id}",
            payload: ['order_id' => $order->id, 'status' => $order->status],
        ));

        AttributeOrder::dispatch((int) $order->id);
    }

    /**
     * An order status changed (or the order was cancelled).
     */
    public function updated(Order $order): void
    {
        IngestTrigger::dispatchDto(new TriggerEvent(
            type: TriggerType::OrderStatusChanged,
            channelId: (int) $order->channel_id,
            customerId: $order->customer_id,
            email: $order->customer_email,
            phone: $order->billing_address?->phone,
            triggerKey: "order-status:{$order->id}:{$order->status}",
            payload: ['order_id' => $order->id, 'status' => $order->status],
        ));

        if ($order->status === 'completed') {
            IngestTrigger::dispatchDto(new TriggerEvent(
                type: TriggerType::OrderCompleted,
                channelId: (int) $order->channel_id,
                customerId: $order->customer_id,
                email: $order->customer_email,
                phone: $order->billing_address?->phone,
                triggerKey: "order-completed:{$order->id}",
                payload: ['order_id' => $order->id, 'status' => $order->status],
            ));
        }
    }
}
