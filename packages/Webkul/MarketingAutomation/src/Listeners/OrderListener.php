<?php

namespace Webkul\MarketingAutomation\Listeners;

use Webkul\MarketingAutomation\DTOs\TriggerEvent;
use Webkul\MarketingAutomation\Enums\TriggerType;
use Webkul\MarketingAutomation\Jobs\IngestTrigger;
use Webkul\Sales\Models\Order;

class OrderListener
{
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
     * A new order was placed.
     */
    public function placed(Order $order): void
    {
        IngestTrigger::dispatchDto(new TriggerEvent(
            type: TriggerType::OrderPlaced,
            channelId: (int) $order->channel_id,
            customerId: $order->customer_id,
            email: $order->customer_email,
            phone: $order->billing_address?->phone,
            triggerKey: "order:{$order->id}",
            payload: ['order_id' => $order->id, 'status' => $order->status],
        ));
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
