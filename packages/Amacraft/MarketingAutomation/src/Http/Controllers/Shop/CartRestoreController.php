<?php

namespace Amacraft\MarketingAutomation\Http\Controllers\Shop;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Webkul\Checkout\Cart as CartHelper;
use Webkul\Checkout\Contracts\Cart;
use Webkul\Checkout\Contracts\CartItem;
use Webkul\Checkout\Repositories\CartRepository;
use Webkul\Customer\Contracts\Customer;
use Amacraft\MarketingAutomation\Contracts\AbandonedCart;
use Amacraft\MarketingAutomation\Enums\MessageEventType;
use Amacraft\MarketingAutomation\Repositories\AbandonedCartRepository;
use Amacraft\MarketingAutomation\Repositories\MessageLogRepository;
use Amacraft\MarketingAutomation\Services\RestoreToken;

class CartRestoreController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct(
        protected AbandonedCartRepository $abandonedCartRepository,
        protected CartRepository $cartRepository,
        protected MessageLogRepository $messageLogRepository,
        protected CartHelper $cartHelper,
        protected RestoreToken $restoreToken,
    ) {}

    /**
     * Put the abandoned cart back into the visitor's session; the token is the capability (FR-012).
     */
    public function restore(Request $request, string $token): RedirectResponse
    {
        $abandoned = $this->abandonedCartRepository->findRestorable($this->restoreToken->hash($token));

        if ($abandoned === null) {
            return redirect()->route('shop.checkout.cart.index')
                ->with('warning', trans('marketing_automation::app.shop.restore.expired'));
        }

        if ($abandoned->cart !== null) {
            $this->restoreCart($abandoned, $abandoned->cart);
        }

        if (filled($messageUuid = (string) $request->query('m'))) {
            $this->messageLogRepository->recordEvent($messageUuid, MessageEventType::Clicked);
        }

        return redirect()->route('shop.checkout.cart.index');
    }

    /**
     * Route the cart to the restore strategy that fits the visitor.
     */
    protected function restoreCart(AbandonedCart $abandoned, Cart $cart): void
    {
        $customer = auth()->guard('customer')->user();

        if ($customer !== null) {
            $this->restoreForCustomer($cart, $customer);

            return;
        }

        if (! empty($cart->customer_id)) {
            $this->copyIntoGuestCart($abandoned, $cart);

            return;
        }

        $this->cartHelper->setCart($this->cartRepository->update(['is_active' => 1], $cart->id));
    }

    /**
     * Activate the customer's own cart or merge a guest cart into it; never touch another customer's cart.
     */
    protected function restoreForCustomer(Cart $cart, Customer $customer): void
    {
        if ((int) $cart->customer_id === (int) $customer->id) {
            $this->cartHelper->activateCart($cart->id);

            return;
        }

        if (! empty($cart->customer_id)) {
            return;
        }

        $this->cartHelper->setCart($this->cartRepository->update(['is_active' => 1], $cart->id));

        $this->cartHelper->mergeCart($customer);
    }

    /**
     * Copy a customer's cart into the guest's cart without a login redirect, and retire the original.
     */
    protected function copyIntoGuestCart(AbandonedCart $abandoned, Cart $cart): void
    {
        $this->cartRepository->update(['is_active' => 0], $cart->id);

        foreach ($cart->items as $item) {
            $this->addToCurrentCart($item);
        }

        $guestCart = $this->cartHelper->getCart();

        if ($guestCart === null) {
            return;
        }

        $this->cartHelper->collectTotals();

        $this->abandonedCartRepository->update(['restored_cart_id' => $guestCart->id], $abandoned->id);
    }

    /**
     * Add one item through the core cart, at today's price; an item that can no longer be sold is skipped.
     */
    protected function addToCurrentCart(CartItem $item): void
    {
        $product = $item->product;

        if (
            $product === null
            || ! $product->isSaleable()
        ) {
            return;
        }

        try {
            $this->cartHelper->addProduct($product, [
                'product_id' => $item->product_id,
                'quantity'   => $item->quantity,
            ] + ($item->additional ?? []));
        } catch (\Exception $exception) {
            report($exception);
        }
    }
}
