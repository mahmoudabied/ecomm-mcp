<?php

use Webkul\MarketingAutomation\Models\Attribution;
use Webkul\MarketingAutomation\Models\Contact;
use Webkul\MarketingAutomation\Models\MessageLog;
use Webkul\MarketingAutomation\Models\ProviderConnection;
use Webkul\MarketingAutomation\Repositories\StepStatRepository;
use Webkul\MarketingAutomation\Services\AttributionService;
use Webkul\Sales\Models\Order;
use Webkul\Sales\Models\OrderAddress;
use Webkul\Sales\Models\OrderPayment;

/**
 * An order placed by the given contact.
 */
function attrOrder(Contact $contact, float $total = 120.00): Order
{
    $order = Order::factory()->create([
        'channel_id'            => $contact->channel_id,
        'customer_id'           => $contact->customer_id,
        'customer_email'        => $contact->customer_id ? null : $contact->email,
        'base_grand_total'      => $total,
        'grand_total'           => $total,
        'base_currency_code'    => 'USD',
        'order_currency_code'   => 'USD',
        'created_at'            => now(),
    ]);

    OrderAddress::factory()->create([
        'order_id'     => $order->id,
        'address_type' => 'order_billing',
        'email'        => $contact->email,
    ]);

    OrderPayment::factory()->create(['order_id' => $order->id]);

    return $order;
}

it('attributes an order to the flow whose message was clicked two days earlier', function () {
    $contact = Contact::factory()->create(['channel_id' => core()->getAllChannels()->first()->id]);

    $log = MessageLog::factory()->create([
        'contact_id'      => $contact->id,
        'channel_id'      => $contact->channel_id,
        'message_channel' => 'email',
        'is_marketing'    => true,
        'clicked_at'      => now()->subDays(2),
    ]);

    $order = attrOrder($contact);

    $attribution = app(AttributionService::class)->attribute($order);

    expect($attribution)->not->toBeNull()
        ->and($attribution->flow_id)->toBe($log->flow_id)
        ->and($attribution->step_key)->toBe($log->step_key)
        ->and($attribution->message_log_id)->toBe($log->id)
        ->and((float) $attribution->revenue)->toBe(120.00)
        ->and($attribution->model)->toBe('click');

    expect(Attribution::query()->count())->toBe(1);
});

it('does not attribute a click older than the window', function () {
    config(['marketing_automation.attribution.settings.click_window_hours' => 120]);

    $contact = Contact::factory()->create(['channel_id' => core()->getAllChannels()->first()->id]);

    MessageLog::factory()->create([
        'contact_id' => $contact->id,
        'channel_id' => $contact->channel_id,
        'clicked_at' => now()->subDays(6),
    ]);

    $order = attrOrder($contact);

    expect(app(AttributionService::class)->attribute($order))->toBeNull()
        ->and(Attribution::query()->count())->toBe(0);
});

it('gives the latest click the order when two flows compete', function () {
    $contact = Contact::factory()->create(['channel_id' => core()->getAllChannels()->first()->id]);

    $older = MessageLog::factory()->create([
        'contact_id' => $contact->id,
        'channel_id' => $contact->channel_id,
        'clicked_at' => now()->subDays(3),
    ]);

    $newer = MessageLog::factory()->create([
        'contact_id' => $contact->id,
        'channel_id' => $contact->channel_id,
        'clicked_at' => now()->subDays(1),
    ]);

    $order = attrOrder($contact);

    $attribution = app(AttributionService::class)->attribute($order);

    expect($attribution->message_log_id)->toBe($newer->id)
        ->and($attribution->flow_id)->toBe($newer->flow_id)
        ->and(Attribution::query()->count())->toBe(1);

    expect($older->fresh())->not->toBeNull();
});

it('attributes a delivered sms inside the delivered window', function () {
    $connection = ProviderConnection::factory()->sms()->create(['driver' => 'twilio']);

    $contact = Contact::factory()->create(['channel_id' => core()->getAllChannels()->first()->id]);

    MessageLog::factory()->create([
        'contact_id'             => $contact->id,
        'channel_id'             => $contact->channel_id,
        'message_channel'        => 'sms',
        'provider_connection_id' => $connection->id,
        'status'                 => 'delivered',
        'delivered_at'           => now()->subHours(10),
    ]);

    $order = attrOrder($contact);

    $attribution = app(AttributionService::class)->attribute($order);

    expect($attribution)->not->toBeNull()
        ->and($attribution->model)->toBe('delivered');
});

it('does not attribute a delivery older than the delivered window', function () {
    $connection = ProviderConnection::factory()->sms()->create(['driver' => 'twilio']);

    $contact = Contact::factory()->create(['channel_id' => core()->getAllChannels()->first()->id]);

    MessageLog::factory()->create([
        'contact_id'             => $contact->id,
        'channel_id'             => $contact->channel_id,
        'message_channel'        => 'sms',
        'provider_connection_id' => $connection->id,
        'status'                 => 'delivered',
        'delivered_at'           => now()->subHours(30),
    ]);

    $order = attrOrder($contact);

    expect(app(AttributionService::class)->attribute($order))->toBeNull();
});

it('keeps a single attribution row when the same order is attributed twice', function () {
    $contact = Contact::factory()->create(['channel_id' => core()->getAllChannels()->first()->id]);

    MessageLog::factory()->create([
        'contact_id' => $contact->id,
        'channel_id' => $contact->channel_id,
        'clicked_at' => now()->subDays(2),
    ]);

    $order = attrOrder($contact);

    $service = app(AttributionService::class);

    $service->attribute($order);

    $second = $service->attribute($order);

    expect(Attribution::query()->count())->toBe(1)
        ->and($second->order_id)->toBe($order->id);
});

it('sums report revenue matching the attribution rows', function () {
    $contact = Contact::factory()->create(['channel_id' => core()->getAllChannels()->first()->id]);

    $log = MessageLog::factory()->create([
        'contact_id' => $contact->id,
        'channel_id' => $contact->channel_id,
        'clicked_at' => now()->subDay(),
    ]);

    $order = attrOrder($contact, 200.00);

    app(AttributionService::class)->attribute($order);

    $report = app(StepStatRepository::class)
        ->report($log->flow_id, now()->subDays(30), now()->addDay(), null);

    expect((float) $report['flow']['revenue'])->toBe(200.00);
});
