<?php

namespace Webkul\MarketingAutomation\Repositories;

use Carbon\CarbonInterface;
use Webkul\Checkout\Models\CartProxy;
use Webkul\Core\Eloquent\Repository;
use Webkul\MarketingAutomation\Contracts\AbandonedCart;
use Webkul\MarketingAutomation\Enums\AbandonedCartStatus;

class AbandonedCartRepository extends Repository
{
    /**
     * Specify model class name.
     */
    public function model(): string
    {
        return 'Webkul\MarketingAutomation\Contracts\AbandonedCart';
    }

    /**
     * Feed the channel's carts idle between the two times to the callback, in chunks.
     */
    public function eachIdleCart(int $channelId, CarbonInterface $idleSince, CarbonInterface $notBefore, callable $callback): void
    {
        CartProxy::modelClass()::query()
            ->where('channel_id', $channelId)
            ->where('is_active', 1)
            ->where('items_count', '>', 0)
            ->where('updated_at', '<=', $idleSince)
            ->where('updated_at', '>=', $notBefore)
            ->with('billing_address')
            ->chunkById(200, fn ($carts) => $carts->each($callback));
    }

    /**
     * The record behind a restore token that is unexpired and not ordered yet.
     */
    public function findRestorable(string $tokenHash): ?AbandonedCart
    {
        return $this->model
            ->where('restore_token_hash', $tokenHash)
            ->where('restore_token_expires_at', '>', now())
            ->where('status', '!=', AbandonedCartStatus::Recovered->value)
            ->first();
    }

    /**
     * Mark the cart's abandoned record reopened, when the shopper came back to it.
     */
    public function markReopened(int $cartId): int
    {
        return $this->model
            ->where('cart_id', $cartId)
            ->where('status', AbandonedCartStatus::Abandoned->value)
            ->update(['status' => AbandonedCartStatus::Reopened->value]);
    }

    /**
     * Mark recovered the records of the ordered cart, or of the cart a restore link created.
     */
    public function markRecovered(int $cartId, int $orderId): int
    {
        return $this->model
            ->where(fn ($query) => $query
                ->where('cart_id', $cartId)
                ->orWhere('restored_cart_id', $cartId))
            ->update([
                'status'             => AbandonedCartStatus::Recovered->value,
                'recovered_order_id' => $orderId,
            ]);
    }
}
