<?php

use Illuminate\Support\Facades\Http;
use Webkul\Customer\Models\Customer;
use Amacraft\Klaviyo\Jobs\HistoricalSync;
use Amacraft\Klaviyo\Models\Connection;
use Amacraft\Klaviyo\Models\OutboxItem;
use Amacraft\Klaviyo\Models\SyncJob;
use Amacraft\MarketingAutomation\Enums\ConsentSource;
use Amacraft\MarketingAutomation\Enums\MessageChannel;
use Amacraft\MarketingAutomation\Services\ConsentService;
use Amacraft\MarketingAutomation\Services\ContactResolver;
use Webkul\Product\Models\Product;
use Webkul\Product\Models\ProductFlat;
use Webkul\Sales\Models\Order;

beforeEach(function () {
    $this->loginAsAdmin();

    Http::fake([
        'a.klaviyo.com/api/*' => Http::response('', 202),
    ]);
});

it('creates one sync job per entity and processes the expected volumes', function () {
    $connection = Connection::factory()->create([
        'channel_id' => core()->getAllChannels()->first()->id,
    ]);

    $channelCode = core()->getAllChannels()->first()->code;

    $customersBefore = Customer::query()->count();
    $ordersBefore = Order::query()
        ->where('channel_id', $connection->channel_id)
        ->where('created_at', '>=', now()->subMonths(12))
        ->count();
    $catalogBefore = DB::table('product_flat')
        ->where('channel', $channelCode)
        ->where('status', 1)
        ->distinct()
        ->count('product_id');
    $profilesBefore = OutboxItem::query()->where('kind', 'profile')->count();
    $catalogRowsBefore = OutboxItem::query()->where('kind', 'catalog_item')->count();
    $placedBefore = OutboxItem::query()->where('kind', 'event')->count();

    $customers = Customer::factory()->count(3)->create();

    // One customer arrives with a granted email consent and a revoked SMS one,
    // so the historical sync carries the opt-in state along (T137).
    $consentedContact = app(ContactResolver::class)
        ->forCustomer($customers->first(), (int) $connection->channel_id);

    app(ConsentService::class)->grant($consentedContact, MessageChannel::Email, ConsentSource::Account);
    app(ConsentService::class)->revoke($consentedContact, MessageChannel::Sms, ConsentSource::Account);

    Order::factory()->create([
        'channel_id'     => $connection->channel_id,
        'customer_email' => 'a@example.com',
        'created_at'     => now()->subMonths(1),
    ]);

    Order::factory()->create([
        'channel_id'     => $connection->channel_id,
        'customer_email' => 'b@example.com',
        'created_at'     => now()->subMonths(6),
    ]);

    Order::factory()->create([
        'channel_id'     => $connection->channel_id,
        'customer_email' => 'old@example.com',
        'created_at'     => now()->subMonths(13),
    ]);

    Product::factory()->count(2)->create(['type' => 'simple'])->each(function ($product) use ($channelCode) {
        ProductFlat::query()->create([
            'product_id' => $product->id,
            'sku'        => $product->sku,
            'type'       => 'simple',
            'name'       => $product->sku,
            'url_key'    => $product->sku,
            'status'     => 1,
            'channel'    => $channelCode,
            'locale'     => 'en',
        ]);
    });

    $this->postJson(route('admin.marketing.klaviyo.sync'), [
        'channel_id' => $connection->channel_id,
        'entities'   => ['customers', 'orders', 'catalog'],
    ])->assertOk();

    expect(SyncJob::query()->where('connection_id', $connection->id)->count())->toBe(3)
        ->and(SyncJob::query()->where('entity', 'customers')->first()->total)->toBe($customersBefore + 3)
        ->and(SyncJob::query()->where('entity', 'orders')->first()->total)->toBe($ordersBefore + 2)
        ->and(SyncJob::query()->where('entity', 'catalog')->first()->total)->toBe($catalogBefore + 2);

    foreach (SyncJob::query()->get() as $job) {
        HistoricalSync::dispatchSync($job->id);
    }

    $placedAfter = OutboxItem::query()
        ->where('kind', 'event')
        ->get()
        ->filter(fn ($item) => ($item->payload['data']['attributes']['metric']['data']['attributes']['name'] ?? null) === 'Placed Order');

    expect(OutboxItem::query()->where('kind', 'profile')->count())->toBe(
        $profilesBefore + ($customersBefore + 3) + ($ordersBefore + 2)
    )
        ->and($placedAfter->count())->toBe($placedBefore + $ordersBefore + 2)
        ->and(OutboxItem::query()->where('kind', 'catalog_item')->count())->toBe($catalogRowsBefore + $catalogBefore + 2);

    $syncedSubscriptions = OutboxItem::query()
        ->where('kind', 'subscription')
        ->get()
        ->filter(fn ($item) => str_contains((string) $item->unique_id, ':subscription:sync:'));

    $emailSubscription = $syncedSubscriptions->first(fn ($item) => ($item->payload['channel'] ?? null) === MessageChannel::Email->value);

    $smsSubscription = $syncedSubscriptions->first(fn ($item) => ($item->payload['channel'] ?? null) === MessageChannel::Sms->value);

    expect($syncedSubscriptions)->toHaveCount(2)
        ->and($emailSubscription?->payload['subscribed'])->toBeTrue()
        ->and($smsSubscription?->payload['subscribed'])->toBeFalse()
        ->and($emailSubscription?->unique_id)->toBe("{$channelCode}:subscription:sync:{$consentedContact->id}:email");

    foreach (SyncJob::query()->get() as $job) {
        expect($job->status)->toBe('completed')
            ->and($job->processed)->toBe($job->total);
    }

    $this->getJson(route('admin.marketing.klaviyo.sync_status'))
        ->assertOk()
        ->assertJsonStructure(['data' => [['entity', 'status', 'processed', 'total']]]);
});
