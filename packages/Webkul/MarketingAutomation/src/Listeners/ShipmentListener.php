<?php

namespace Webkul\MarketingAutomation\Listeners;

use Webkul\MarketingAutomation\DTOs\TriggerEvent;
use Webkul\MarketingAutomation\Enums\TriggerType;
use Webkul\MarketingAutomation\Jobs\IngestTrigger;
use Webkul\Sales\Models\Shipment;

class ShipmentListener
{
    /**
     * Register the listeners for the automation module.
     */
    public function subscribe($events): void
    {
        $events->listen('sales.shipment.save.after', [self::class, 'saved']);
    }

    /**
     * A shipment was created for an order.
     */
    public function saved(Shipment $shipment): void
    {
        $order = $shipment->order;

        IngestTrigger::dispatchDto(new TriggerEvent(
            type: TriggerType::OrderShipped,
            channelId: (int) $order->channel_id,
            customerId: $order->customer_id,
            email: $order->customer_email,
            phone: $order->billing_address?->phone,
            triggerKey: "shipment:{$shipment->id}",
            payload: ['order_id' => $order->id, 'shipment_id' => $shipment->id],
        ));
    }
}
