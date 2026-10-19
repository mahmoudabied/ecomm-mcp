<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Webkul\Core\Models\SubscribersList;
use Webkul\Customer\Models\Customer;
use Webkul\Inventory\Models\InventorySource;
use Amacraft\MarketingAutomation\Enums\FlowStatus;
use Amacraft\MarketingAutomation\Listeners\ProductListener;
use Amacraft\MarketingAutomation\Models\BackInStockSubscription;
use Amacraft\MarketingAutomation\Models\Contact;
use Amacraft\MarketingAutomation\Models\Enrolment;
use Amacraft\MarketingAutomation\Models\Flow;
use Amacraft\MarketingAutomation\Repositories\FlowRepository;
use Webkul\Notification\Events\CreateOrderNotification;
use Webkul\Product\Models\Product;
use Webkul\Product\Models\ProductInventory;
use Webkul\Sales\Models\Order;
use Webkul\Sales\Models\OrderPayment;
use Webkul\Sales\Models\Shipment;

beforeEach(function () {
    core()->getAllChannels()->first()->forceFill(['timezone' => 'UTC'])->save();

    // Bagisto's own notification jobs are tenant-aware; keep them out of the queue.
    Queue::fake([CreateOrderNotification::class]);

    // Daily triggers are guarded by a per-day cache key.
    Cache::flush();
});

function makeTriggerFlow(string $triggerType, array $overrides = []): Flow
{
    $flow = Flow::factory()->create([
        'trigger_type'   => $triggerType,
        'status'         => FlowStatus::Draft->value,
        ...$overrides,
    ]);

    $flow->update(['draft_definition' => [
        'entry' => 's1',
        'steps' => [
            's1' => ['type' => 'send_email', 'subject' => ['en' => 'Hi'], 'blocks' => ['en' => [['type' => 'text', 'text' => 'x']]], 'validity_hours' => 24, 'next' => 's2'],
            's2' => ['type' => 'exit'],
        ],
    ]]);

    $flow->channels()->sync([core()->getAllChannels()->first()->id]);

    app(FlowRepository::class)->activate($flow);

    return $flow->refresh();
}

function soleTriggerKey(Flow $flow): ?string
{
    return Enrolment::query()->where('flow_id', $flow->id)->value('trigger_key');
}

it('enrols on customer registration', function () {
    $flow = makeTriggerFlow('customer_registered');

    $customer = Customer::factory()->create(['channel_id' => core()->getAllChannels()->first()->id]);

    Event::dispatch('customer.registration.after', $customer);

    expect(soleTriggerKey($flow))->toBe('customer:'.$customer->id);
});

it('enrols on newsletter subscription', function () {
    $flow = makeTriggerFlow('newsletter_subscribed');

    $subscriber = SubscribersList::factory()->create([
        'channel_id'    => core()->getAllChannels()->first()->id,
        'is_subscribed' => 1,
    ]);

    Event::dispatch('customer.subscription.after', $subscriber);

    expect(soleTriggerKey($flow))->toBe('subscribe:'.$subscriber->id);
});

it('enrols on order placement', function () {
    $flow = makeTriggerFlow('order_placed');

    $order = Order::factory()
        ->has(OrderPayment::factory()->state(['method' => 'cashondelivery']), 'payment')
        ->create([
            'channel_id' => core()->getAllChannels()->first()->id,
            'status'     => 'pending',
        ]);

    Event::dispatch('checkout.order.save.after', $order);

    expect(soleTriggerKey($flow))->toBe('order:'.$order->id);
});

it('enrols on order status change and completion', function () {
    $statusFlow = makeTriggerFlow('order_status_changed');
    $completedFlow = makeTriggerFlow('order_completed');

    $order = Order::factory()->create([
        'channel_id' => core()->getAllChannels()->first()->id,
        'status'     => 'processing',
    ]);

    $order->update(['status' => 'completed']);

    Event::dispatch('sales.order.update-status.after', $order);

    expect(soleTriggerKey($statusFlow))->toBe('order-status:'.$order->id.':completed')
        ->and(soleTriggerKey($completedFlow))->toBe('order-completed:'.$order->id);
});

it('enrols on shipment save', function () {
    $flow = makeTriggerFlow('order_shipped');

    $order = Order::factory()->create([
        'channel_id' => core()->getAllChannels()->first()->id,
        'status'     => 'processing',
    ]);

    $shipment = Shipment::factory()->create(['order_id' => $order->id]);

    Event::dispatch('sales.shipment.save.after', $shipment);

    expect(soleTriggerKey($flow))->toBe('shipment:'.$shipment->id);
});

it('enrols back-in-stock subscriptions when stock returns', function () {
    $flow = makeTriggerFlow('back_in_stock');

    $product = Product::factory()->create(['type' => 'simple']);

    $customer = Customer::factory()->create();

    $contact = Contact::factory()->create([
        'channel_id'  => core()->getAllChannels()->first()->id,
        'customer_id' => $customer->id,
        'email'       => $customer->email,
    ]);

    $subscription = BackInStockSubscription::factory()->create([
        'contact_id' => $contact->id,
        'product_id' => $product->id,
        'channel_id' => $contact->channel_id,
        'status'     => 'waiting',
    ]);

    $inventory = ProductInventory::factory()->create([
        'product_id'          => $product->id,
        'qty'                 => 0,
        'inventory_source_id' => InventorySource::query()->first()->id,
    ]);

    // The admin controller fires "before" with the id before saving the restock.
    // Core product listeners need fully attributed products, so the automation
    // listener is invoked directly — its event wiring is registered globally.
    $listener = app(ProductListener::class);

    $listener->updating($product->id);

    $inventory->update(['qty' => 5]);

    $listener->updated($product);

    expect(soleTriggerKey($flow))->toBe('bis:'.$subscription->id)
        ->and($subscription->refresh()->status)->toBe('notified');
});

it('enrols inactive customers once per day key', function () {
    // Travel first: flows activated later must satisfy activated_at <= now().
    $this->travelTo(now()->utc()->setTime(9, 0));

    $flow = makeTriggerFlow('customer_inactive', ['trigger_config' => ['inactive_days' => 90]]);

    $customer = Customer::factory()->create([
        'channel_id' => core()->getAllChannels()->first()->id,
        'created_at' => now()->subDays(200),
    ]);

    Order::factory()->create([
        'customer_id'    => $customer->id,
        'customer_email' => $customer->email,
        'channel_id'     => $customer->channel_id,
        'created_at'     => now()->subDays(90),
    ]);

    $this->artisan('automation:daily-triggers');

    // The scan resolves the contact itself (contacts are created lazily).
    $contact = Contact::query()->where('customer_id', $customer->id)->first();

    // The key carries the run date, so each local day enrolls at most once.
    $expectedKey = 'inactive:'.$contact->id.':'.now()->utc()->format('Y-m-d');

    expect(soleTriggerKey($flow))->toBe($expectedKey);
});

it('enrols birthdays in the channel timezone', function () {
    $this->travelTo(now()->utc()->setTime(9, 0));

    $flow = makeTriggerFlow('customer_birthday');

    $customer = Customer::factory()->create([
        'channel_id'    => core()->getAllChannels()->first()->id,
        'date_of_birth' => now()->subYears(30)->format('Y-m-d'),
    ]);

    // The daily scan runs at 09:00 in the channel timezone.
    $this->artisan('automation:daily-triggers');

    // Factory customers can share the birthday by chance; assert ours got in.
    expect(Enrolment::query()
        ->where('flow_id', $flow->id)
        ->where('trigger_key', 'birthday:'.$customer->id.':'.now()->format('Y'))
        ->exists())->toBeTrue();
});

it('matches the inactive day in the channel timezone and ignores newer orders', function () {
    $channel = core()->getAllChannels()->first();

    $channel->forceFill(['timezone' => 'Asia/Riyadh'])->save();

    // 09:00 in Riyadh (UTC+3) is 06:00 UTC.
    $this->travelTo(now()->utc()->setTime(6, 0));

    $flow = makeTriggerFlow('customer_inactive', ['trigger_config' => ['inactive_days' => 90]]);

    // 23:30 Riyadh time on the target day is already the next day in the app
    // timezone (Asia/Kolkata by default), so a server-side DATE() would miss it;
    // timestamps are stored as wall-clock time in the app timezone.
    $targetOrderAt = now()->setTimezone('Asia/Riyadh')->subDays(90)->setTime(23, 30)->setTimezone(config('app.timezone'));

    $inactive = Customer::factory()->create(['channel_id' => $channel->id]);

    Order::factory()->create([
        'customer_id'    => $inactive->id,
        'customer_email' => $inactive->email,
        'channel_id'     => $channel->id,
        'created_at'     => $targetOrderAt,
    ]);

    // Ordered on the target day too, but came back since: not inactive.
    $returning = Customer::factory()->create(['channel_id' => $channel->id]);

    foreach ([$targetOrderAt, now()->subDays(10)] as $createdAt) {
        Order::factory()->create([
            'customer_id'    => $returning->id,
            'customer_email' => $returning->email,
            'channel_id'     => $channel->id,
            'created_at'     => $createdAt,
        ]);
    }

    $this->artisan('automation:daily-triggers');

    $enrolledContactIds = Enrolment::query()->where('flow_id', $flow->id)->pluck('contact_id');

    $inactiveContact = Contact::query()->where('customer_id', $inactive->id)->first();

    expect($inactiveContact)->not->toBeNull()
        ->and($enrolledContactIds->contains($inactiveContact->id))->toBeTrue()
        ->and(Contact::query()->where('customer_id', $returning->id)->exists())->toBeFalse();
});

it('skips the inactive scan for flows on other sales channels', function () {
    $this->travelTo(now()->utc()->setTime(9, 0));

    $flow = makeTriggerFlow('customer_inactive', ['trigger_config' => ['inactive_days' => 90]]);

    // Detach the flow from the only channel the scan visits.
    $flow->channels()->detach();

    $customer = Customer::factory()->create(['channel_id' => core()->getAllChannels()->first()->id]);

    Order::factory()->create([
        'customer_id'    => $customer->id,
        'customer_email' => $customer->email,
        'channel_id'     => $customer->channel_id,
        'created_at'     => now()->subDays(90),
    ]);

    $this->artisan('automation:daily-triggers');

    expect(Contact::query()->where('customer_id', $customer->id)->exists())->toBeFalse();
});
