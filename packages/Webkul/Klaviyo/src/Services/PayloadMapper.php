<?php

namespace Webkul\Klaviyo\Services;

use Illuminate\Support\Str;
use Webkul\Checkout\Contracts\Cart;
use Webkul\Checkout\Contracts\CartItem;
use Webkul\Core\Models\Channel;
use Webkul\Customer\Models\Customer;
use Webkul\MarketingAutomation\Models\Contact;
use Webkul\MarketingAutomation\Services\ContactResolver;
use Webkul\Product\Models\Product;
use Webkul\Sales\Models\Order;
use Webkul\Sales\Models\OrderItem;
use Webkul\Sales\Models\Refund;

class PayloadMapper
{
    /**
     * Channel codes already looked up during this job or request.
     *
     * @var array<int, string>
     */
    protected array $channelCodeCache = [];

    /**
     * The JSON:API profile attributes of a person.
     */
    public function profile(Customer|Contact $person): array
    {
        $channelCode = $this->channelCode($person->channel_id);

        $attributes = [
            'email'       => $person->email,
            'first_name'  => $person->first_name,
            'last_name'   => $person->last_name,
            'properties'  => ['locale' => $person->locale],
        ];

        if ($phone = $this->phoneNumber($person)) {
            $attributes['phone_number'] = $phone;
        }

        if ($person->customer_id) {
            $attributes['external_id'] = "{$channelCode}:customer:{$person->customer_id}";
        }

        return $attributes;
    }

    /**
     * The Placed Order event payload of an order.
     */
    public function placedOrder(Order $order): array
    {
        return $this->orderEvent($order, 'Placed Order', [
            'value' => (float) $order->base_grand_total,
        ]);
    }

    /**
     * The Ordered Product event payload of one order line.
     */
    public function orderedProduct(Order $order, OrderItem $item): array
    {
        $payload = $this->orderEvent($order, 'Ordered Product', [
            'value' => (float) $item->total,
        ]);

        $payload['data']['attributes']['unique_id'] .= ':'.$item->id;

        return $payload;
    }

    /**
     * The Fulfilled Order event payload of an order.
     */
    public function fulfilledOrder(Order $order): array
    {
        return $this->orderEvent($order, 'Fulfilled Order');
    }

    /**
     * The Cancelled Order event payload of an order.
     */
    public function cancelledOrder(Order $order): array
    {
        return $this->orderEvent($order, 'Cancelled Order');
    }

    /**
     * The Refunded Order event payload of a refund.
     */
    public function refundedOrder(Refund $refund): array
    {
        $order = $refund->order;

        $payload = $this->orderEvent($order, 'Refunded Order', [
            'value' => (float) $refund->base_grand_total,
        ]);

        $payload['data']['attributes']['unique_id'] .= ':'.$refund->id;

        return $payload;
    }

    /**
     * The Viewed Product event payload of a product view.
     */
    public function viewedProduct(Product $product, Contact $contact): array
    {
        return $this->productEvent($product, $contact, 'Viewed Product', 'viewed_product', [
            'ProductName' => $product->name,
            'ProductURL'  => $this->productUrl($product),
            'ImageURL'    => $this->productImageUrl($product),
        ]);
    }

    /**
     * The Added to Cart event payload of a cart line.
     */
    public function addedToCart(CartItem $item, Contact $contact): array
    {
        $product = $item->product;

        return $this->productEvent($product, $contact, 'Added to Cart', 'added_to_cart', [
            'ProductName' => $item->name,
            'Quantity'    => (int) $item->quantity,
            'ProductURL'  => $product ? $this->productUrl($product) : null,
            'ImageURL'    => $product ? $this->productImageUrl($product) : null,
            'value'       => (float) $item->total,
        ]);
    }

    /**
     * The Started Checkout event payload of a cart.
     */
    public function startedCheckout(Cart $cart, Contact $contact): array
    {
        $channelCode = $this->channelCode($cart->channel_id);

        $attributes = [
            'metric'    => $this->metric('Started Checkout'),
            'profile'   => $this->profileRef($contact),
            'unique_id' => "{$channelCode}:started_checkout:{$cart->id}",
            'time'      => now()->toIso8601String(),
            'value'     => (float) $cart->grand_total,
        ];

        return $this->envelope($attributes);
    }

    /**
     * The catalog item JSON:API body of a product.
     */
    public function catalogItem(Product $product, Channel $channel): array
    {
        $channelCode = $this->channelCode($channel->id);

        return [
            'data' => [
                'type'       => 'catalog-item',
                'id'         => "{$channelCode}:product:{$product->id}",
                'attributes' => [
                    'external_id'  => "{$channelCode}:product:{$product->id}",
                    'title'        => $product->name,
                    'description'  => (string) $product->description,
                    'url'          => $this->productUrl($product),
                    'image_path'   => $this->productImageUrl($product),
                    'integrations' => [
                        'key' => 'api',
                    ],
                ],
            ],
        ];
    }

    /**
     * Build one order event payload with the standard order properties.
     */
    protected function orderEvent(Order $order, string $metricName, array $extra = []): array
    {
        $channelCode = $this->channelCode($order->channel_id);

        $attributes = array_merge([
            'metric'     => $this->metric($metricName),
            'profile'    => $this->profileRef($this->orderContact($order)),
            'properties' => $this->orderProperties($order),
            'unique_id'  => "{$channelCode}:".$this->metricSlug($metricName).":{$order->id}",
            'time'       => optional($order->created_at ?? now())->toIso8601String(),
            'value'      => (float) $order->base_grand_total,
        ], $extra);

        return $this->envelope($attributes);
    }

    /**
     * Build one product event payload.
     */
    protected function productEvent(Product $product, Contact $contact, string $metricName, string $metricSlug, array $properties): array
    {
        $channelCode = $this->channelCode($contact->channel_id);

        $attributes = [
            'metric'     => $this->metric($metricName),
            'profile'    => $this->profileRef($contact),
            'properties' => array_merge([
                'ProductID' => (string) $product->id,
                'SKU'       => $product->sku,
            ], $properties),
            'unique_id' => "{$channelCode}:{$metricSlug}:{$product->id}",
            'time'      => now()->toIso8601String(),
        ];

        return $this->envelope($attributes);
    }

    /**
     * The Klaviyo order property set.
     */
    protected function orderProperties(Order $order): array
    {
        return [
            'OrderId'       => $order->increment_id,
            'Categories'    => $order->items->map(fn ($item) => $item->product?->categories->pluck('name'))->flatten()->filter()->unique()->values()->all(),
            'ItemNames'     => $order->items->pluck('name')->all(),
            'Items'         => $order->items->map(fn ($item) => [
                'ProductID'   => (string) $item->product_id,
                'SKU'         => $item->sku,
                'ProductName' => $item->name,
                'Quantity'    => (int) $item->qty_ordered,
                'ItemPrice'   => (float) $item->base_price,
                'RowTotal'    => (float) $item->base_total,
                'ProductURL'  => $item->product ? $this->productUrl($item->product) : null,
                'ImageURL'    => $item->product ? $this->productImageUrl($item->product) : null,
            ])->all(),
            'DiscountCode'  => $order->coupon_code,
            'DiscountValue' => (float) $order->base_discount_amount,
        ];
    }

    /**
     * The profile reference of the order's customer, creating the contact when needed.
     */
    protected function orderContact(Order $order): Contact
    {
        return app(ContactResolver::class)->resolve(
            channelId: (int) $order->channel_id,
            customerId: $order->customer_id,
            email: $order->customer_email,
            phone: $order->billing_address?->phone,
        );
    }

    /**
     * The metric JSON:API fragment.
     */
    protected function metric(string $name): array
    {
        return [
            'data' => [
                'type'       => 'metric',
                'attributes' => ['name' => $name],
            ],
        ];
    }

    /**
     * The profile JSON:API fragment of a contact.
     */
    protected function profileRef(Contact $contact): array
    {
        return [
            'data' => [
                'type'       => 'profile',
                'attributes' => $this->profile($contact),
            ],
        ];
    }

    /**
     * Wrap attributes into the event envelope.
     */
    protected function envelope(array $attributes): array
    {
        return [
            'data' => [
                'type'       => 'event',
                'attributes' => $attributes,
            ],
        ];
    }

    /**
     * The snake_case slug of a metric name, matching the unique_id pattern.
     */
    protected function metricSlug(string $name): string
    {
        return str_replace('-', '_', Str::snake($name));
    }

    /**
     * The code of a sales channel, memoized per instance.
     */
    protected function channelCode(int|string|null $channelId): string
    {
        $channelId = (int) $channelId;

        if (! isset($this->channelCodeCache[$channelId])) {
            $this->channelCodeCache[$channelId] = Channel::query()
                ->where('id', $channelId)
                ->value('code') ?? 'default';
        }

        return $this->channelCodeCache[$channelId];
    }

    /**
     * The E.164 phone of a person, when any.
     */
    protected function phoneNumber(Customer|Contact $person): ?string
    {
        $raw = $person instanceof Contact ? $person->phone_e164 : $person->phone;

        return $raw ?: null;
    }

    /**
     * The storefront URL of a product.
     */
    protected function productUrl(Product $product): string
    {
        if (! $product->url_key) {
            return route('shop.home.index');
        }

        return route('shop.product_or_category.index', $product->url_key);
    }

    /**
     * The base image URL of a product.
     */
    protected function productImageUrl(Product $product): string
    {
        return (string) (product_image()->getProductBaseImage($product)['original_image_url'] ?? '');
    }
}
