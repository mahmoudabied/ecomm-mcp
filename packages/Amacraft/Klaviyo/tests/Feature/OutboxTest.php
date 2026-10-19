<?php

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Webkul\Checkout\Models\Cart;
use Webkul\Checkout\Models\CartItem;
use Webkul\Customer\Models\Customer;
use Amacraft\Klaviyo\Jobs\FlushOutboxBatch;
use Amacraft\Klaviyo\Models\Connection;
use Amacraft\Klaviyo\Models\OutboxItem;
use Amacraft\Klaviyo\Tests\KlaviyoFixtures;
use Webkul\Product\Models\Product;
use Webkul\Sales\Models\Order;
use Webkul\Sales\Models\OrderAddress;
use Webkul\Sales\Models\OrderItem;
use Webkul\Sales\Models\OrderPayment;
use Webkul\Sales\Models\Refund;

beforeEach(function () {
    Queue::fake([FlushOutboxBatch::class]);
});

/**
 * An order with two line items for the given customer.
 */
function outboxOrder(?Customer $customer = null): Order
{
    $order = Order::factory()->create([
        'channel_id'          => core()->getAllChannels()->first()->id,
        'customer_id'         => $customer?->id,
        'customer_email'      => $customer?->email ?? 'guest@example.com',
        'base_grand_total'    => 150.00,
        'grand_total'         => 150.00,
        'base_currency_code'  => 'USD',
        'order_currency_code' => 'USD',
        'increment_id'        => 'ORDER-'.rand(1000, 9999),
    ]);

    foreach ([['Item One', 'SKU-1', 1], ['Item Two', 'SKU-2', 2]] as [$name, $sku, $qty]) {
        $product = Product::factory()->create(['type' => 'simple', 'sku' => $sku]);

        OrderItem::factory()->create([
            'order_id'     => $order->id,
            'parent_id'    => null,
            'product_id'   => $product->id,
            'product_type' => Product::class,
            'type'         => 'simple',
            'qty_ordered'  => $qty,
            'name'         => $name,
            'sku'          => $sku,
        ]);
    }

    OrderAddress::factory()->create([
        'order_id'     => $order->id,
        'address_type' => 'order_billing',
        'email'        => $order->customer_email,
    ]);

    OrderPayment::factory()->create(['order_id' => $order->id]);

    return $order->refresh();
}

/**
 * An active cart with one item for the given customer.
 */
function outboxCart(?Customer $customer = null): Cart
{
    $product = Product::factory()->create(['type' => 'simple']);

    $cart = Cart::factory()->create([
        'customer_id' => $customer?->id,
        'is_active'   => 1,
        'items_count' => 1,
    ]);

    CartItem::factory()->create([
        'cart_id'    => $cart->id,
        'product_id' => $product->id,
        'sku'        => $product->sku,
        'type'       => 'simple',
        'name'       => $product->name,
        'quantity'   => 1,
    ]);

    return $cart->refresh();
}

it('queues a profile, a placed order and one event per line item when an order is placed', function () {
    $connection = Connection::factory()->create([
        'channel_id' => core()->getAllChannels()->first()->id,
    ]);

    $order = outboxOrder();

    Event::dispatch('checkout.order.save.after', $order);

    $kinds = OutboxItem::query()->where('connection_id', $connection->id)->get();

    $metricNames = $kinds
        ->pluck('payload')
        ->map(fn ($payload) => $payload['data']['attributes']['metric']['data']['attributes']['name'] ?? null)
        ->filter()
        ->values();

    expect($kinds->where('kind', 'event')->count())->toBe(3)
        ->and($metricNames)->toContain('Placed Order')
        ->and($metricNames->filter(fn ($name) => $name === 'Ordered Product')->count())->toBe(2)
        ->and($kinds->pluck('unique_id')->filter(fn ($id) => str_contains((string) $id, 'ordered_product'))->count())->toBe(2)
        ->and($kinds->where('kind', 'profile')->count())->toBe(1);
});

it('flushes the outbox and marks each row sent', function () {
    $connection = Connection::factory()->create([
        'channel_id' => core()->getAllChannels()->first()->id,
    ]);

    Event::dispatch('checkout.order.save.after', outboxOrder());

    Http::fake([
        'a.klaviyo.com/api/*' => Http::response('', 202),
    ]);

    $this->artisan('klaviyo:flush-outbox')->assertSuccessful();

    expect(OutboxItem::query()->where('connection_id', $connection->id)->where('status', 'sent')->count())->toBe(4);

    Http::assertSent(function ($request) {
        return $request->url() === 'https://a.klaviyo.com/api/events'
            && str_contains($request['data']['attributes']['unique_id'], ':placed_order:');
    });
});

it('keeps a row pending with a pushed back availability after a 429', function () {
    Connection::factory()->create([
        'channel_id' => core()->getAllChannels()->first()->id,
    ]);

    Event::dispatch('checkout.order.save.after', outboxOrder());

    Http::fake([
        'a.klaviyo.com/api/*' => Http::response(KlaviyoFixtures::body('events_429'), 429),
    ]);

    $this->artisan('klaviyo:flush-outbox')->assertSuccessful();

    $item = OutboxItem::query()->where('status', 'pending')->first();

    expect($item->attempts)->toBe(1)
        ->and($item->available_at->greaterThan(now()->addSeconds(30)))->toBeTrue()
        ->and($item->status)->toBe('pending');
});

it('drops duplicate outbox rows for the same order event', function () {
    Connection::factory()->create([
        'channel_id' => core()->getAllChannels()->first()->id,
    ]);

    $order = outboxOrder();

    Event::dispatch('checkout.order.save.after', $order);

    Event::dispatch('checkout.order.save.after', $order);

    expect(OutboxItem::query()->count())->toBe(4);
});

it('delivers once when Klaviyo is down then up', function () {
    Connection::factory()->create([
        'channel_id' => core()->getAllChannels()->first()->id,
    ]);

    Event::dispatch('checkout.order.save.after', outboxOrder());

    Http::fake([
        'a.klaviyo.com/api/*' => Http::sequence()
            ->pushFailedConnection()
            ->push('', 202)
            ->whenEmpty(Http::response('', 202)),
    ]);

    $this->artisan('klaviyo:flush-outbox')->assertSuccessful();

    $item = OutboxItem::query()->first();

    expect($item->status)->toBe('pending')
        ->and($item->attempts)->toBe(1);

    $this->travel(3)->minutes();

    $this->artisan('klaviyo:flush-outbox')->assertSuccessful();

    $item = $item->fresh();

    expect($item->status)->toBe('sent')
        ->and($item->attempts)->toBe(1)
        ->and(OutboxItem::query()->where('status', 'sent')->count())->toBe(4);
});

it('maps every core event to the right metric name', function () {
    $connection = Connection::factory()->create([
        'channel_id' => core()->getAllChannels()->first()->id,
    ]);

    $customer = Customer::factory()->create();
    $order = outboxOrder($customer);

    Event::dispatch('checkout.order.save.after', $order);

    $order->status = 'completed';
    Event::dispatch('sales.order.update-status.after', $order);

    $order->status = 'canceled';
    Event::dispatch('sales.order.cancel.after', $order);

    $refund = Refund::factory()->make(['order_id' => $order->id, 'base_grand_total' => 10]);
    Event::dispatch('sales.refund.save.after', $refund);

    $this->actingAs($customer, 'customer');

    $cart = outboxCart($customer);

    session()->put('cart', (object) ['id' => $cart->id]);

    Event::dispatch('checkout.load.index');

    Event::dispatch('checkout.cart.add.after', $cart);

    $metricNames = [];

    foreach (OutboxItem::query()->where('connection_id', $connection->id)->get() as $item) {
        $metricNames[] = $item->payload['data']['attributes']['metric']['data']['attributes']['name'] ?? null;
    }

    expect($metricNames)->toContain('Placed Order')
        ->and($metricNames)->toContain('Ordered Product')
        ->and($metricNames)->toContain('Fulfilled Order')
        ->and($metricNames)->toContain('Cancelled Order')
        ->and($metricNames)->toContain('Refunded Order')
        ->and($metricNames)->toContain('Started Checkout')
        ->and($metricNames)->toContain('Added to Cart');

    expect(OutboxItem::query()->where('connection_id', $connection->id)->where('kind', 'profile')->count())->toBeGreaterThanOrEqual(1);
});

it('records one viewed product per hour per visitor', function () {
    $channelId = core()->getAllChannels()->first()->id;

    Connection::factory()->create(['channel_id' => $channelId]);

    $customer = Customer::factory()->create();
    $product = Product::factory()->create(['type' => 'simple']);

    $this->actingAs($customer, 'customer')
        ->post(route('klaviyo.track.viewed-product'), ['product_id' => $product->id])
        ->assertNoContent();

    expect(OutboxItem::query()->where('kind', 'event')->where('unique_id', 'like', '%viewed_product%')->count())->toBe(1);

    $this->actingAs($customer, 'customer')
        ->post(route('klaviyo.track.viewed-product'), ['product_id' => $product->id])
        ->assertNoContent();

    expect(OutboxItem::query()->where('kind', 'event')->where('unique_id', 'like', '%viewed_product%')->count())->toBe(1);
});

it('ignores anonymous viewed-product beacons', function () {
    Connection::factory()->create([
        'channel_id' => core()->getAllChannels()->first()->id,
    ]);

    $product = Product::factory()->create(['type' => 'simple']);

    $this->post(route('klaviyo.track.viewed-product'), ['product_id' => $product->id])
        ->assertNoContent();

    expect(OutboxItem::query()->count())->toBe(0);
});
