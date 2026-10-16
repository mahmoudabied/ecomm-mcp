<?php

namespace Webkul\Klaviyo\Listeners;

use Webkul\Checkout\Cart as CartHelper;
use Webkul\Checkout\Contracts\Cart as CartContract;
use Webkul\Checkout\Contracts\CartItem as CartItemContract;
use Webkul\Core\Models\Channel;
use Webkul\Klaviyo\Services\OutboxWriter;
use Webkul\Klaviyo\Services\PayloadMapper;
use Webkul\MarketingAutomation\Enums\ConsentSource;
use Webkul\MarketingAutomation\Enums\ConsentStatus;
use Webkul\MarketingAutomation\Models\Contact;
use Webkul\MarketingAutomation\Services\ConsentService;
use Webkul\MarketingAutomation\Services\ContactResolver;
use Webkul\Product\Models\Product;
use Webkul\Sales\Contracts\Order;
use Webkul\Sales\Models\Refund;

class StoreEventsListener
{
    /**
     * Create a new store events listener instance.
     */
    public function __construct(
        protected OutboxWriter $outbox,
        protected PayloadMapper $mapper,
        protected ContactResolver $contactResolver,
        protected ConsentService $consentService,
        protected CartHelper $cartHelper,
    ) {}

    /**
     * Register the listeners for the Klaviyo module.
     */
    public function subscribe($events): void
    {
        $events->listen('checkout.order.save.after', [self::class, 'orderPlaced']);
        $events->listen('sales.order.update-status.after', [self::class, 'orderStatusChanged']);
        $events->listen('sales.order.cancel.after', [self::class, 'orderCancelled']);
        $events->listen('sales.refund.save.after', [self::class, 'orderRefunded']);
        $events->listen('checkout.cart.add.after', [self::class, 'addedToCart']);
        $events->listen('checkout.load.index', [self::class, 'startedCheckout']);
        $events->listen('catalog.product.update.after', [self::class, 'productUpdated']);
        $events->listen('marketing_automation.consent.changed', [self::class, 'consentChanged']);
    }

    /**
     * A profile upsert, the Placed Order and one Ordered Product per line.
     */
    public function orderPlaced(Order $order): void
    {
        $this->writeForOrderChannel($order, function () use ($order) {
            $profile = $this->mapper->profile($this->mapperContact($order));

            return [
                ['profile', "{$this->channelCode($order)}:profile:{$order->id}", $this->profileEnvelope($profile)],
                ['event', $this->orderId($order, 'placed_order'), $this->mapper->placedOrder($order)],
                ...$order->items->map(fn ($item) => [
                    'event',
                    $this->orderId($order, 'ordered_product').':'.$item->id,
                    $this->mapper->orderedProduct($order, $item),
                ])->all(),
            ];
        });
    }

    /**
     * A Fulfilled Order when the status turned completed.
     */
    public function orderStatusChanged(Order $order): void
    {
        if ($order->status !== 'completed') {
            return;
        }

        $this->writeForOrderChannel($order, fn () => [
            ['event', $this->orderId($order, 'fulfilled_order'), $this->mapper->fulfilledOrder($order)],
        ]);
    }

    /**
     * A Cancelled Order when an order is cancelled.
     */
    public function orderCancelled(Order $order): void
    {
        $this->writeForOrderChannel($order, fn () => [
            ['event', $this->orderId($order, 'cancelled_order'), $this->mapper->cancelledOrder($order)],
        ]);
    }

    /**
     * A Refunded Order when a refund is saved.
     */
    public function orderRefunded(Refund $refund): void
    {
        $order = $refund->order;

        if ($order === null) {
            return;
        }

        $this->writeForOrderChannel($order, function () use ($refund) {
            $payload = $this->mapper->refundedOrder($refund);
            $uniqueId = substr($payload['data']['attributes']['unique_id'], 0, 191);

            return [['event', $uniqueId, $payload]];
        });
    }

    /**
     * An Added to Cart event, only when the shopper is known.
     */
    public function addedToCart(mixed $payload): void
    {
        $cart = $this->cartFrom($payload);

        if ($cart === null || ! $this->hasConnection((int) $cart->channel_id)) {
            return;
        }

        $item = $cart->items->last();

        if ($item === null) {
            return;
        }

        $contact = $this->cartContact($cart);

        if ($contact === null || ! $contact->email) {
            return;
        }

        $writerPayload = $this->mapper->addedToCart($item, $contact);
        $uniqueId = substr($writerPayload['data']['attributes']['unique_id'], 0, 191);

        $this->outbox->writeToChannel((int) $cart->channel_id, fn () => [['event', $uniqueId, $writerPayload]]);
    }

    /**
     * A Started Checkout event, only when the shopper is known.
     */
    public function startedCheckout(): void
    {
        $cart = $this->currentCart();

        if ($cart === null || ! $this->hasConnection((int) $cart->channel_id)) {
            return;
        }

        $contact = $this->cartContact($cart);

        if ($contact === null || ! $contact->email) {
            return;
        }

        $payload = $this->mapper->startedCheckout($cart, $contact);
        $uniqueId = substr($payload['data']['attributes']['unique_id'], 0, 191);

        $this->outbox->writeToChannel((int) $cart->channel_id, fn () => [['event', $uniqueId, $payload]]);
    }

    /**
     * A catalog item upsert after a product changes.
     */
    public function productUpdated(Product $product): void
    {
        $channelId = (int) (core()->getCurrentChannel()->id ?? 0);

        $this->outbox->writeToChannel($channelId, function ($connection) use ($product) {
            $payload = $this->mapper->catalogItem($product, $connection->channel);

            return [['catalog_item', $payload['data']['id'], $payload]];
        });
    }

    /**
     * A subscription push when store consent changes, skipping Klaviyo's own echoes.
     */
    public function consentChanged($consent, $event): void
    {
        if ($consent->source === ConsentSource::Klaviyo) {
            return;
        }

        $contact = $consent->contact()->first();

        if ($contact === null) {
            return;
        }

        $subscribing = $consent->status === ConsentStatus::Granted;

        $this->outbox->writeToChannel((int) $contact->channel_id, function ($connection) use ($contact, $consent, $subscribing) {
            $uniqueId = sprintf(
                '%s:subscription:%s:%s:%s',
                $this->channelCodeOf($connection),
                $contact->id,
                $consent->channel->value,
                $consent->changed_at?->timestamp ?? now()->timestamp
            );

            return [[
                'subscription',
                $uniqueId,
                [
                    'channel'          => $consent->channel,
                    'subscribed'       => $subscribing,
                    'profile'          => $this->mapper->profile($contact),
                    'event_attributes' => [
                        'metric'    => ['data' => ['type' => 'metric', 'attributes' => ['name' => $subscribing ? 'Subscribed to Marketing' : 'Unsubscribed from Marketing']]],
                        'profile'   => ['data' => ['type' => 'profile', 'attributes' => $this->mapper->profile($contact)]],
                        'unique_id' => $uniqueId,
                        'time'      => now()->toIso8601String(),
                    ],
                ],
            ]];
        });
    }

    /**
     * Whether the channel has an active connection; the cheapest guard first.
     */
    protected function hasConnection(int $channelId): bool
    {
        return $this->outbox->activeConnectionsOf($channelId) !== [];
    }

    /**
     * Write every piece produced by the maker on the order's channel.
     *
     * @param  callable(): array<int, array{0: string, 1: string, 2: array}>  $make
     */
    protected function writeForOrderChannel(Order $order, callable $make): void
    {
        $channelId = (int) $order->channel_id;

        if (! $this->hasConnection($channelId)) {
            return;
        }

        $this->outbox->writeToChannel($channelId, function ($connection) use ($make) {
            return $make();
        });
    }

    /**
     * The [kind, unique_id, payload] tuples of an order share the same shape.
     */
    protected function orderId(Order $order, string $metricSlug): string
    {
        return substr($this->channelCode($order).":{$metricSlug}:{$order->id}", 0, 191);
    }

    /**
     * The channel code of an order's sales channel.
     */
    protected function channelCode(Order $order): string
    {
        return $this->channelCodeOfChannelId((int) $order->channel_id);
    }

    /**
     * The channel code of a connection's channel.
     */
    protected function channelCodeOf($connection): string
    {
        return $this->channelCodeOfChannelId((int) $connection->channel_id);
    }

    /**
     * Look up one channel code.
     */
    protected function channelCodeOfChannelId(int $channelId): string
    {
        return Channel::query()->where('id', $channelId)->value('code') ?? 'default';
    }

    /**
     * The contact backing an order's profile rows.
     */
    protected function mapperContact(Order $order): Contact
    {
        return $this->contactResolver->resolve(
            channelId: (int) $order->channel_id,
            customerId: $order->customer_id,
            email: $order->customer_email,
            phone: $order->billing_address?->phone,
        );
    }

    /**
     * Wrap profile attributes into the profile-import envelope.
     */
    protected function profileEnvelope(array $attributes): array
    {
        return [
            'data' => [
                'type'       => 'profile',
                'attributes' => $attributes,
            ],
        ];
    }

    /**
     * The cart behind the event payload: a cart or a cart item.
     */
    protected function cartFrom(mixed $payload): ?CartContract
    {
        if ($payload instanceof CartContract) {
            return $payload;
        }

        if ($payload instanceof CartItemContract) {
            return $payload->cart;
        }

        return $this->currentCart();
    }

    /**
     * The session cart, when any.
     */
    protected function currentCart(): ?CartContract
    {
        $cart = $this->cartHelper->getCart();

        return $cart?->id ? $cart : null;
    }

    /**
     * The contact of a cart's shopper, when known.
     */
    protected function cartContact(CartContract $cart): ?Contact
    {
        if ($cart->customer_id) {
            return $this->contactResolver->forCustomer($cart->customer, (int) $cart->channel_id);
        }

        if (filled($cart->customer_email)) {
            return $this->contactResolver->resolve(
                channelId: (int) $cart->channel_id,
                customerId: null,
                email: $cart->customer_email,
                phone: $cart->billing_address?->phone,
                names: ['first' => $cart->customer_first_name, 'last' => $cart->customer_last_name],
            );
        }

        return null;
    }
}
