<?php

namespace Amacraft\MarketingAutomation\Rendering;

use Webkul\Checkout\Contracts\Cart;
use Webkul\Checkout\Repositories\CartRepository;
use Webkul\Core\Repositories\LocaleRepository;
use Amacraft\MarketingAutomation\Contracts\Enrolment;
use Amacraft\MarketingAutomation\Repositories\AbandonedCartRepository;
use Amacraft\MarketingAutomation\Services\RestoreToken;
use Webkul\Product\Contracts\Product;
use Webkul\Sales\Contracts\Order;
use Webkul\Sales\Repositories\OrderRepository;

class ContextBuilder
{
    /**
     * Create a new context builder instance.
     */
    public function __construct(
        protected AbandonedCartRepository $abandonedCartRepository,
        protected CartRepository $cartRepository,
        protected LocaleRepository $localeRepository,
        protected OrderRepository $orderRepository,
        protected RestoreToken $restoreToken,
    ) {}

    /**
     * Build the render context for an enrolment's current step.
     */
    public function forEnrolment(Enrolment $enrolment): array
    {
        $context = $this->baseContext($enrolment->channel_id, $enrolment->contact?->locale);

        $context['customer'] = [
            'first_name' => $enrolment->contact?->first_name,
            'last_name'  => $enrolment->contact?->last_name,
            'email'      => $enrolment->contact?->email,
        ];

        $payload = $enrolment->trigger_payload ?? [];

        if (! empty($payload['cart_id'])) {
            $context['cart'] = $this->cartSection($this->cartRepository->find($payload['cart_id']));

            if ($restoreUrl = $this->restoreUrlFor((int) ($payload['abandoned_cart_id'] ?? 0))) {
                $context['cart']['restore_url'] = $restoreUrl;
            }
        }

        if (! empty($payload['order_id'])) {
            $context['order'] = $this->orderSection($this->orderRepository->find($payload['order_id']));
        }

        return $context;
    }

    /**
     * Build a fixed sample context for previews and test sends.
     */
    public function forSample(int $channelId, string $locale): array
    {
        $context = $this->baseContext($channelId, $locale);

        $context['customer'] = [
            'first_name' => 'Sara',
            'last_name'  => 'Ahmed',
            'email'      => 'sara@example.com',
        ];

        $context['cart'] = [
            'total'       => core()->formatPrice(150.00, 'USD'),
            'items_count' => 2,
            'items'       => [],
        ];

        $context['order'] = [
            'increment_id' => '#1001',
            'total'        => core()->formatPrice(150.00, 'USD'),
            'items'        => [],
        ];

        return $context;
    }

    /**
     * The signed restore link of the abandoned cart, rebuilt from the record.
     */
    protected function restoreUrlFor(int $abandonedCartId): ?string
    {
        if ($abandonedCartId === 0) {
            return null;
        }

        $abandoned = $this->abandonedCartRepository->find($abandonedCartId);

        if ($abandoned === null) {
            return null;
        }

        return route('shop.automation.cart.restore', $this->restoreToken->for($abandoned));
    }

    /**
     * Build the store-level context shared by every message.
     */
    protected function baseContext(int $channelId, ?string $locale): array
    {
        $locale = $locale ?: config('app.locale');

        return [
            'store' => [
                'name' => core()->getCurrentChannel()->name,
                'url'  => route('shop.home.index'),
            ],
            'channel_id' => $channelId,
            'locale'     => $locale,
            'direction'  => $this->directionFor($locale),
        ];
    }

    /**
     * Describe the cart, skipping items that can no longer be sold.
     */
    protected function cartSection(?Cart $cart): array
    {
        if ($cart === null) {
            return [];
        }

        $items = [];

        foreach ($cart->items as $item) {
            $product = $item->product;

            if (
                $product === null
                || ! $product->isSaleable()
            ) {
                continue;
            }

            $items[] = [
                'product_id'      => $product->id,
                'name'            => $item->name,
                'qty'             => $item->quantity,
                'price_formatted' => core()->formatPrice($item->price, $cart->cart_currency_code),
                'image_url'       => $this->imageUrl($product),
                'url'             => $this->productUrl($product),
            ];
        }

        return [
            'total'       => core()->formatPrice($cart->grand_total, $cart->cart_currency_code),
            'items_count' => $cart->items_count,
            'items'       => $items,
        ];
    }

    /**
     * Describe the order for post-purchase and status messages.
     */
    protected function orderSection(?Order $order): array
    {
        if ($order === null) {
            return [];
        }

        $items = [];

        foreach ($order->items as $item) {
            $items[] = [
                'product_id'      => $item->product_id,
                'name'            => $item->name,
                'qty'             => $item->qty_ordered,
                'price_formatted' => core()->formatPrice($item->price, $order->order_currency_code),
            ];
        }

        return [
            'increment_id' => $order->increment_id,
            'total'        => core()->formatPrice($order->grand_total, $order->order_currency_code),
            'items'        => $items,
            'tracking_url' => route('shop.customers.account.orders.view', $order->id),
            'review_url'   => $this->reviewUrl($order),
        ];
    }

    /**
     * Point the review link at the first ordered product.
     */
    protected function reviewUrl(Order $order): ?string
    {
        $product = $order->items->first()?->product;

        if ($product === null) {
            return null;
        }

        return $this->productUrl($product).'#review';
    }

    /**
     * Resolve the storefront URL of a product.
     */
    protected function productUrl(Product $product): string
    {
        return route('shop.product_or_category.index', $product->url_key);
    }

    /**
     * Resolve the medium product image URL.
     */
    protected function imageUrl(Product $product): string
    {
        return product_image()->getProductBaseImage($product)['medium_image_url'];
    }

    /**
     * Get the text direction of a locale, defaulting to left-to-right.
     */
    protected function directionFor(string $locale): string
    {
        return $this->localeRepository->findOneByField('code', $locale)?->direction ?: 'ltr';
    }
}
