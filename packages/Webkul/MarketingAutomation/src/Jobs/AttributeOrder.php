<?php

namespace Webkul\MarketingAutomation\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Spatie\Multitenancy\Jobs\NotTenantAware;
use Webkul\MarketingAutomation\Services\AttributionService;
use Webkul\Sales\Models\Order;

class AttributeOrder implements NotTenantAware, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(
        protected int $orderId,
    ) {
        $this->onQueue('automation');
    }

    /**
     * Credit the order to the last clicked or delivered marketing message.
     */
    public function handle(AttributionService $attributionService): void
    {
        $order = Order::query()->find($this->orderId);

        if ($order === null) {
            return;
        }

        $attributionService->attribute($order);
    }
}
