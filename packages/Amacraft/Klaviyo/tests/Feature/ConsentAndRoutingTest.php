<?php

use Illuminate\Support\Facades\Http;
use Webkul\Checkout\Models\Cart;
use Webkul\Checkout\Models\CartItem;
use Webkul\Core\Models\CoreConfig;
use Amacraft\Klaviyo\Models\Connection;
use Amacraft\Klaviyo\Models\OutboxItem;
use Amacraft\Klaviyo\Tests\KlaviyoFixtures;
use Amacraft\MarketingAutomation\Enums\ConsentSource;
use Amacraft\MarketingAutomation\Enums\ConsentStatus;
use Amacraft\MarketingAutomation\Enums\MessageChannel;
use Amacraft\MarketingAutomation\Enums\RoutingTarget;
use Amacraft\MarketingAutomation\Models\AbandonedCart;
use Amacraft\MarketingAutomation\Models\Contact;
use Amacraft\MarketingAutomation\Models\Enrolment;
use Amacraft\MarketingAutomation\Models\Flow;
use Amacraft\MarketingAutomation\Repositories\FlowRepository;
use Amacraft\MarketingAutomation\Services\ConsentService;
use Amacraft\MarketingAutomation\Services\Settings;
use Webkul\Product\Models\Product;

function consentFlow(): void
{
    $flow = Flow::factory()->create([
        'template_key'         => 'abandoned_cart',
        'trigger_type'         => 'cart_abandoned',
        'trigger_config'       => ['abandon_delay_minutes' => 60],
        'reentry_window_hours' => 168,
    ]);

    $flow->update(['draft_definition' => [
        'entry' => 's1',
        'steps' => [
            's1' => [
                'type'           => 'send_email',
                'subject'        => ['en' => 'Come back'],
                'blocks'         => ['en' => [['type' => 'text', 'text' => 'Miss you']]],
                'validity_hours' => 24,
                'next'           => null,
            ],
        ],
    ]]);

    $flow->channels()->sync([core()->getAllChannels()->first()->id]);

    app(FlowRepository::class)->activate($flow);
}

it('pulls Klaviyo unsubscribes and revokes the matching store consents', function () {
    $channelId = core()->getAllChannels()->first()->id;

    $connection = Connection::factory()->create([
        'channel_id'     => $channelId,
        'consent_cursor' => '2026-10-09 00:00:00',
    ]);

    $sara = Contact::factory()->create([
        'channel_id' => $channelId,
        'email'      => 'sara@example.com',
    ]);

    $omar = Contact::factory()->create([
        'channel_id' => $channelId,
        'email'      => 'omar@example.com',
        'phone_e164' => '+966501234567',
    ]);

    $consents = app(ConsentService::class);

    $consents->grant($sara, MessageChannel::Email, ConsentSource::Checkout);
    $consents->grant($omar, MessageChannel::Sms, ConsentSource::Checkout);

    Http::fake([
        'a.klaviyo.com/api/metrics*'  => Http::response(KlaviyoFixtures::body('metrics'), 200),
        'a.klaviyo.com/api/events*'   => Http::response(KlaviyoFixtures::body('unsubscribed_events_page'), 200),
    ]);

    $this->artisan('klaviyo:pull-consent')->assertSuccessful();

    expect($consents->status($sara->fresh(), MessageChannel::Email))->toBe(ConsentStatus::Revoked)
        ->and($consents->status($omar->fresh(), MessageChannel::Sms))->toBe(ConsentStatus::Revoked)
        ->and($sara->fresh()->consents()->first()->source)->toBe(ConsentSource::Klaviyo)
        ->and($connection->fresh()->consent_cursor->equalTo('2026-10-11T11:45:00+00:00'))->toBeTrue()
        ->and($connection->fresh()->unsubscribed_metric_ids)->toBe(['METRIC_UNSUB_EMAIL', 'METRIC_UNSUB_SMS']);
});

it('pushes store consent changes to the outbox as subscription rows', function () {
    $channelId = core()->getAllChannels()->first()->id;

    Connection::factory()->create(['channel_id' => $channelId]);

    $contact = Contact::factory()->create(['channel_id' => $channelId]);

    app(ConsentService::class)->grant($contact, MessageChannel::Email, ConsentSource::Account);

    $subscription = OutboxItem::query()->where('kind', 'subscription')->first();

    expect($subscription)->not->toBeNull()
        ->and($subscription->connection_id)->toBe(Connection::query()->first()->id);
});

it('does not echo Klaviyo sourced consent changes back to the outbox', function () {
    Connection::factory()->create([
        'channel_id' => core()->getAllChannels()->first()->id,
    ]);

    $contact = Contact::factory()->create([
        'channel_id' => core()->getAllChannels()->first()->id,
    ]);

    app(ConsentService::class)->grant($contact, MessageChannel::Email, ConsentSource::Klaviyo);

    expect(OutboxItem::query()->where('kind', 'subscription')->count())->toBe(0);
});

it('does not enrol abandoned carts natively when routing sends them to Klaviyo', function () {
    $channel = core()->getAllChannels()->first();

    Connection::factory()->create(['channel_id' => $channel->id]);

    CoreConfig::query()->create([
        'code'         => 'marketing_automation.routing.settings.abandoned_cart',
        'value'        => 'klaviyo',
        'channel_code' => $channel->code,
    ]);

    consentFlow();

    $contact = Contact::factory()->create([
        'channel_id' => $channel->id,
        'email'      => 'abandoned@example.com',
    ]);

    $product = Product::factory()->create(['type' => 'simple']);

    $cart = Cart::factory()->create([
        'channel_id'     => $channel->id,
        'is_active'      => 1,
        'items_count'    => 1,
        'customer_email' => $contact->email,
    ]);

    CartItem::factory()->create([
        'cart_id'    => $cart->id,
        'product_id' => $product->id,
        'sku'        => $product->sku,
        'type'       => 'simple',
        'name'       => $product->name,
        'quantity'   => 1,
    ]);

    Cart::query()->where('id', $cart->id)->update(['updated_at' => now()->subMinutes(61)]);

    $this->artisan('automation:detect-abandoned-carts')->assertSuccessful();

    expect(Enrolment::query()->count())->toBe(0)
        ->and(AbandonedCart::query()->count())->toBe(1);
});

it('falls back to native routing when a Klaviyo option is stored without an active connection', function () {
    $channel = core()->getAllChannels()->first();

    CoreConfig::query()->create([
        'code'         => 'marketing_automation.routing.settings.abandoned_cart',
        'value'        => 'klaviyo',
        'channel_code' => $channel->code,
    ]);

    expect(app(Settings::class)->routing($channel->code, 'abandoned_cart'))->toBe(RoutingTarget::Native);
});
