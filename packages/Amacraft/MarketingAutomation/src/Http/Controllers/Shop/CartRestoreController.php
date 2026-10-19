<?php

namespace Amacraft\MarketingAutomation\Http\Controllers\Shop;

use Amacraft\MarketingAutomation\Contracts\AbandonedCart;
use Amacraft\MarketingAutomation\Enums\MessageEventType;
use Amacraft\MarketingAutomation\Repositories\AbandonedCartRepository;
use Amacraft\MarketingAutomation\Repositories\MessageLogRepository;
use Amacraft\MarketingAutomation\Services\RestoreToken;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Webkul\Checkout\Contracts\Cart as CartContract;
use Webkul\Checkout\Contracts\CartItem;
use Webkul\Checkout\Facades\Cart;
use Webkul\Checkout\Repositories\CartRepository;
use Webkul\Customer\Contracts\Customer;

class CartRestoreController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct(
        protected AbandonedCartRepository $abandonedCartRepository,
        protected CartRepository $cartRepository,
        protected MessageLogRepository $messageLogRepository,
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
    protected function restoreCart(AbandonedCart $abandoned, CartContract $cart): void
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

        Cart::setCart($this->cartRepository->update(['is_active' => 1], $cart->id));
    }

    /**
     * Activate the customer's own cart or merge a guest cart into it; never touch another customer's cart.
     */
    protected function restoreForCustomer(CartContract $cart, Customer $customer): void
    {
        if ((int) $cart->customer_id === (int) $customer->id) {
            Cart::activateCart($cart->id);

            return;
        }

        if (! empty($cart->customer_id)) {
            return;
        }

        Cart::setCart($this->cartRepository->update(['is_active' => 1], $cart->id));

        Cart::mergeCart($customer);
    }

    /**
     * Copy a customer's cart into the guest's cart without a login redirect, and retire the original.
     */
    protected function copyIntoGuestCart(AbandonedCart $abandoned, CartContract $cart): void
    {
        $this->cartRepository->update(['is_active' => 0], $cart->id);

        foreach ($cart->items as $item) {
            $this->addToCurrentCart($item);
        }

        $guestCart = Cart::getCart();

        if ($guestCart === null) {
            return;
        }

        Cart::collectTotals();

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
            Cart::addProduct($product, [
                'product_id' => $item->product_id,
                'quantity'   => $item->quantity,
            ] + ($item->additional ?? []));
        } catch (\Exception $exception) {
            report($exception);
        }
    }
}
