<?php

namespace Amacraft\MarketingAutomation\Listeners;

use Illuminate\Support\Facades\Cache;
use Amacraft\MarketingAutomation\Jobs\ProcessBackInStockSubscriptions;
use Webkul\Product\Models\Product;
use Webkul\Product\Models\ProductInventory;

class ProductListener
{
    /**
     * Register the listeners for the automation module.
     */
    public function subscribe($events): void
    {
        $events->listen('catalog.product.update.before', [self::class, 'updating']);
        $events->listen('catalog.product.update.after', [self::class, 'updated']);
    }

    /**
     * Remember the stock level before the update.
     */
    public function updating(int $productId): void
    {
        $qty = $this->totalQty($productId);

        Cache::put("marketing_automation.stock.{$productId}", $qty, now()->addHours(6));
    }

    /**
     * Enrol waiting subscribers when a product is back in stock. The scan is
     * queued so the admin save request only flips the cache marker.
     */
    public function updated(Product $product): void
    {
        $previousQty = Cache::pull("marketing_automation.stock.{$product->id}");

        if ($previousQty === null) {
            return;
        }

        $newQty = $this->totalQty($product->id);

        if ($previousQty > 0 || $newQty <= 0) {
            return;
        }

        ProcessBackInStockSubscriptions::dispatch($product->id);
    }

    /**
     * Sum the inventory quantities of a product.
     */
    protected function totalQty(int $productId): int
    {
        return (int) ProductInventory::query()
            ->where('product_id', $productId)
            ->sum('qty');
    }
}
