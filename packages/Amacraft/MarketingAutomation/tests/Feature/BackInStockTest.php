<?php

use Amacraft\MarketingAutomation\Database\Seeders\FlowTemplates;
use Amacraft\MarketingAutomation\Listeners\ProductListener;
use Amacraft\MarketingAutomation\Models\BackInStockSubscription;
use Amacraft\MarketingAutomation\Models\Contact;
use Amacraft\MarketingAutomation\Models\Enrolment;
use Amacraft\MarketingAutomation\Models\Flow;
use Amacraft\MarketingAutomation\Models\MessageLog;
use Amacraft\MarketingAutomation\Models\ProviderConnection;
use Amacraft\MarketingAutomation\Repositories\FlowRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Webkul\Inventory\Models\InventorySource;
use Webkul\Product\Models\Product;
use Webkul\Product\Models\ProductInventory;

function bisFlow(): Flow
{
    bisSmsDefault((int) core()->getAllChannels()->first()->id);

    $flow = Flow::factory()->create([
        'trigger_type' => 'back_in_stock',
        'type'         => 'transactional',
    ]);

    $flow->update(['draft_definition' => [
        'entry' => 's1',
        'steps' => [
            's1' => [
                'type'           => 'send_email',
                'subject'        => ['en' => 'Good news — it is back!'],
                'blocks'         => ['en' => [['type' => 'text', 'text' => 'The product is available again.']]],
                'validity_hours' => 12,

                'fallback_channel' => 'sms',
                'fallback_step'    => [
                    'body'           => ['en' => 'Back in stock! Shop it now: {{ store.url }}'],
                    'validity_hours' => 12,
                ],

                'next' => 's2',
            ],

            's2' => ['type' => 'exit'],
        ],
    ]]);

    $flow->channels()->sync([core()->getAllChannels()->first()->id]);

    app(FlowRepository::class)->activate($flow);

    return $flow->refresh();
}

function bisSmsDefault(int $channelId): ProviderConnection
{
    $connection = ProviderConnection::factory()->create([
        'message_channel'  => 'sms',
        'driver'           => 'twilio',
        'credentials'      => ['account_sid' => 'ACtest1234567890', 'auth_token' => 'twilio-token'],
        'sender'           => '+15550001111',
        'status'           => 'connected',
    ]);

    DB::table('automation_provider_channel_defaults')->updateOrInsert(
        [
            'channel_id'      => $channelId,
            'message_channel' => 'sms',
        ],
        [
            'provider_connection_id' => $connection->id,
            'created_at'             => now(),
            'updated_at'             => now(),
        ],
    );

    return $connection;
}

it('ships the back in stock template as transactional without a re-entry window', function () {
    expect(FlowTemplates::find('back_in_stock')['type'])->toBe('transactional')
        ->and(FlowTemplates::find('back_in_stock')['reentry_window_hours'])->toBe(0);
});

it('sends a guest phone-only subscriber the restock message', function () {
    $channelId = (int) core()->getAllChannels()->first()->id;

    $flow = bisFlow();

    $connection = bisSmsDefault($channelId);

    $product = Product::factory()->create(['type' => 'simple']);

    $this->postJson(route('shop.automation.back-in-stock'), [
        'product_id'      => $product->id,
        'phone'           => '+966501234567',
        'consent_channel' => 'sms',
    ])->assertCreated();

    $contact = Contact::query()->where('phone_e164', '+966501234567')->sole();

    expect($contact->email)->toBeNull();

    Http::fake([
        'api.twilio.com/*' => Http::response(['sid' => 'SM1'], 201),
    ]);

    restock($product);

    $enrolment = Enrolment::query()->where('flow_id', $flow->id)->where('contact_id', $contact->id)->sole();

    expect($enrolment->trigger_key)->toBe(BackInStockSubscription::query()->sole()->trigger_key)
        ->and(MessageLog::query()->where('contact_id', $contact->id)->where('message_channel', 'sms')->count())->toBe(1);

    Http::assertSent(fn ($request) => str_starts_with((string) $request->url(), 'https://api.twilio.com/2010-04-01/Accounts/ACtest1234567890/Messages.json')
        && $request['To'] === '+966501234567');
});

it('rotates the trigger key on re-subscribe so re-enrolment is never blocked', function () {
    $channelId = (int) core()->getAllChannels()->first()->id;

    $flow = bisFlow();

    bisSmsDefault($channelId);

    $product = Product::factory()->create(['type' => 'simple']);

    Http::fake([
        'api.twilio.com/*' => Http::response(['sid' => 'SM1'], 201),
    ]);

    $subscribe = fn () => $this->postJson(route('shop.automation.back-in-stock'), [
        'product_id'      => $product->id,
        'phone'           => '+966501234567',
        'consent_channel' => 'sms',
    ])->assertCreated();

    $subscribe();

    $subscription = BackInStockSubscription::query()->sole();

    $firstKey = $subscription->trigger_key;

    restock($product);

    expect(Enrolment::query()->where('flow_id', $flow->id)->count())->toBe(1);

    $this->travelTo(now()->addHours($flow->reentry_window_hours + 1));

    $subscribe();

    expect($subscription->refresh()->status)->toBe('waiting')
        ->and($subscription->trigger_key)->not->toBe($firstKey)
        ->and(BackInStockSubscription::query()->where('trigger_key', $firstKey)->count())->toBe(0);

    restock($product);

    expect(Enrolment::query()->where('flow_id', $flow->id)->count())->toBe(2)
        ->and($subscription->refresh()->status)->toBe('notified');
});

it('notifies a shopper about a second product within the same month', function () {
    $flow = bisFlow();

    $flow->update(['reentry_window_hours' => FlowTemplates::find('back_in_stock')['reentry_window_hours']]);

    Http::fake([
        'api.twilio.com/*' => Http::response(['sid' => 'SM1'], 201),
    ]);

    foreach (Product::factory()->count(2)->create(['type' => 'simple']) as $product) {
        $this->postJson(route('shop.automation.back-in-stock'), [
            'product_id'      => $product->id,
            'phone'           => '+966501234569',
            'consent_channel' => 'sms',
        ])->assertCreated();

        restock($product);
    }

    expect(Enrolment::query()->where('flow_id', $flow->id)->count())->toBe(2)
        ->and(BackInStockSubscription::query()->where('status', 'notified')->count())->toBe(2);
});

it('never grafts a guest phone onto the contact that owns the email', function () {
    $channelId = (int) core()->getAllChannels()->first()->id;

    bisFlow();

    $victim = Contact::factory()->create([
        'channel_id' => $channelId,
        'email'      => 'victim@example.com',
        'phone_e164' => null,
    ]);

    $product = Product::factory()->create(['type' => 'simple']);

    $this->postJson(route('shop.automation.back-in-stock'), [
        'product_id'      => $product->id,
        'email'           => 'victim@example.com',
        'phone'           => '+966501234570',
        'consent_channel' => 'sms',
    ])->assertCreated();

    Http::fake([
        'api.twilio.com/*' => Http::response(['sid' => 'SM1'], 201),
    ]);

    restock($product);

    expect($victim->refresh()->phone_e164)->toBeNull()
        ->and(Enrolment::query()->where('contact_id', $victim->id)->count())->toBe(0);
});

it('checks the IP limit before spending a recipient attempt', function () {
    $product = Product::factory()->create(['type' => 'simple']);

    foreach (range(1, 10) as $attempt) {
        RateLimiter::hit('bis-ip:127.0.0.1', 86400);
    }

    $this->postJson(route('shop.automation.back-in-stock'), [
        'product_id'      => $product->id,
        'phone'           => '+966501234571',
        'consent_channel' => 'sms',
    ])->assertUnprocessable();

    expect(RateLimiter::attempts('bis-phone:+966501234571'))->toBe(0);
});

it('throttles subscription requests per email per day', function () {
    $product = Product::factory()->create(['type' => 'simple']);

    foreach (range(1, 3) as $attempt) {
        $this->postJson(route('shop.automation.back-in-stock'), [
            'product_id'      => $product->id,
            'email'           => 'Same@Example.com',
            'consent_channel' => 'email',
        ])->assertCreated();
    }

    $this->postJson(route('shop.automation.back-in-stock'), [
        'product_id'      => $product->id,
        'email'           => 'same@example.com',
        'consent_channel' => 'email',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('email');
});

it('rejects an unparsable phone number', function () {
    $product = Product::factory()->create(['type' => 'simple']);

    $this->postJson(route('shop.automation.back-in-stock'), [
        'product_id'      => $product->id,
        'phone'           => 'not-a-phone',
        'consent_channel' => 'sms',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('phone');

    expect(Contact::query()->count())->toBe(0);
});

it('throttles subscription requests per phone per day', function () {
    $product = Product::factory()->create(['type' => 'simple']);

    $payload = fn () => [
        'product_id'      => $product->id,
        'phone'           => '+966501234568',
        'consent_channel' => 'sms',
    ];

    $this->postJson(route('shop.automation.back-in-stock'), $payload())->assertCreated();

    $this->postJson(route('shop.automation.back-in-stock'), $payload())->assertCreated();

    $this->postJson(route('shop.automation.back-in-stock'), $payload())->assertCreated();

    $this->postJson(route('shop.automation.back-in-stock'), $payload())
        ->assertUnprocessable()
        ->assertJsonValidationErrors('phone');
});

/**
 * Take a product from out of stock to in stock through the listener pair.
 */
function restock(Product $product): void
{
    $inventory = ProductInventory::query()->updateOrCreate(
        [
            'product_id'          => $product->id,
            'inventory_source_id' => InventorySource::query()->first()->id,
        ],
        ['qty' => 0],
    );

    $listener = app(ProductListener::class);

    $listener->updating($product->id);

    $inventory->update(['qty' => 5]);

    $listener->updated($product);
}
