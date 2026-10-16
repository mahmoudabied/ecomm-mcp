<?php

namespace Webkul\MarketingAutomation\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Webkul\MarketingAutomation\Enums\MessageChannel;
use Webkul\MarketingAutomation\Models\Attribution;
use Webkul\MarketingAutomation\Models\Contact;
use Webkul\MarketingAutomation\Models\MessageLog;
use Webkul\Sales\Models\Order;

class AttributionService
{
    /**
     * Create a new attribution service instance.
     */
    public function __construct(
        protected ContactResolver $contactResolver,
        protected Settings $settings,
        protected StatsRecorder $statsRecorder,
    ) {}

    /**
     * Credit the order to the last message the customer clicked, or to the
     * last delivered SMS/WhatsApp when nothing was clicked. One order is
     * attributed at most once (SC-007).
     */
    public function attribute(Order $order): ?Attribution
    {
        $log = $this->lastClickedLog($order)
            ?? $this->lastDeliveredLog($order);

        if ($log === null) {
            return null;
        }

        try {
            $attribution = Attribution::query()->create([
                'order_id'       => $order->id,
                'flow_id'        => $log->flow_id,
                'step_key'       => (string) $log->step_key,
                'message_log_id' => $log->id,
                'channel_id'     => $log->channel_id,
                'model'          => $log->clicked_at !== null ? 'click' : 'delivered',
                'revenue'        => (float) $order->base_grand_total,
                'currency'       => (string) $order->base_currency_code,
                'attributed_at'  => now(),
            ]);
        } catch (UniqueConstraintViolationException) {
            return Attribution::query()->where('order_id', $order->id)->first();
        }

        // The converted count stays owned by the exit goal; attribution only
        // adds the revenue so one order is never counted twice.
        $this->statsRecorder->increment(
            (int) $log->flow_id,
            (string) $log->step_key,
            (int) $log->channel_id,
            'converted',
            revenue: (float) $order->base_grand_total,
            by: 0,
        );

        return $attribution;
    }

    /**
     * The latest clicked, non-test message of the order's contacts inside the
     * click window that started when the order was created.
     */
    protected function lastClickedLog(Order $order): ?MessageLog
    {
        $windowHours = $this->settings->clickWindowHours((string) $order->channel?->code);

        return $this->logsFor($order)
            ->whereNotNull('clicked_at')
            ->where('clicked_at', '>=', $order->created_at->copy()->subHours($windowHours))
            ->orderByDesc('clicked_at')
            ->first();
    }

    /**
     * The latest delivered SMS/WhatsApp without a click, inside the delivered
     * window; email deliveries carry no receipt, so they never attribute.
     */
    protected function lastDeliveredLog(Order $order): ?MessageLog
    {
        $windowHours = $this->settings->deliveredWindowHours((string) $order->channel?->code);

        return $this->logsFor($order)
            ->whereIn('message_channel', [MessageChannel::Sms->value, MessageChannel::Whatsapp->value])
            ->whereNull('clicked_at')
            ->whereNotNull('delivered_at')
            ->where('delivered_at', '>=', $order->created_at->copy()->subHours($windowHours))
            ->orderByDesc('delivered_at')
            ->first();
    }

    /**
     * The non-test message logs of the contacts behind the order.
     */
    protected function logsFor(Order $order): Builder
    {
        return MessageLog::query()
            ->where('is_test', false)
            ->whereIn('contact_id', $this->contactIdsFor($order));
    }

    /**
     * The automation contacts matching the order's customer or email address
     * inside the order's sales channel.
     *
     * @return array<int, int>
     */
    protected function contactIdsFor(Order $order): array
    {
        if ($order->customer_id === null && blank($order->customer_email)) {
            return [];
        }

        return Contact::query()
            ->where('channel_id', $order->channel_id)
            ->where(function ($query) use ($order) {
                if ($order->customer_id !== null) {
                    $query->where('customer_id', $order->customer_id);
                }

                if (filled($order->customer_email)) {
                    $query->orWhere('email', $order->customer_email);
                }
            })
            ->pluck('id')
            ->all();
    }
}
