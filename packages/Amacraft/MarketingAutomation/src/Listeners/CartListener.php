<?php

namespace Amacraft\MarketingAutomation\Listeners;

use Webkul\Checkout\Cart as CartHelper;
use Webkul\Checkout\Contracts\Cart;
use Webkul\Checkout\Contracts\CartItem;
use Amacraft\MarketingAutomation\Repositories\AbandonedCartRepository;
use Webkul\Sales\Contracts\Order;

class CartListener
{
    /**
     * Create a new cart listener instance.
     */
    public function __construct(
        protected AbandonedCartRepository $abandonedCartRepository,
        protected CartHelper $cartHelper,
    ) {}

    /**
     * Register the listeners for the automation module.
     */
    public function subscribe($events): void
    {
        $events->listen('checkout.cart.add.after', [self::class, 'changed']);
        $events->listen('checkout.cart.update.after', [self::class, 'changed']);
        $events->listen('checkout.cart.delete.after', [self::class, 'changed']);
        $events->listen('checkout.order.save.after', [self::class, 'orderPlaced']);
    }

    /**
     * The shopper came back to a cart that was already detected as abandoned.
     */
    public function changed(mixed $payload): void
    {
        $cart = $this->cartFrom($payload);

        if ($cart !== null) {
            $this->abandonedCartRepository->markReopened($cart->id);
        }
    }

    /**
     * An order was placed from the abandoned cart, or from the cart its restore link created.
     */
    public function orderPlaced(Order $order): void
    {
        if (! empty($order->cart_id)) {
            $this->abandonedCartRepository->markRecovered((int) $order->cart_id, $order->id);
        }
    }

    /**
     * The cart behind the event payload: a cart, a cart item, or on delete only an item id.
     */
    protected function cartFrom(mixed $payload): ?Cart
    {
        if ($payload instanceof Cart) {
            return $payload;
        }

        if ($payload instanceof CartItem) {
            return $payload->cart;
        }

        return $this->cartHelper->getCart();
    }
}
