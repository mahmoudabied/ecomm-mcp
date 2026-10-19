<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Webkul\Checkout\Models\Cart;
use Webkul\Checkout\Models\CartItem;
use Amacraft\MarketingAutomation\Engine\StepRunner;
use Amacraft\MarketingAutomation\Jobs\ProcessEnrolmentStep;
use Amacraft\MarketingAutomation\Models\Contact;
use Amacraft\MarketingAutomation\Models\Enrolment;
use Amacraft\MarketingAutomation\Models\Flow;
use Amacraft\MarketingAutomation\Models\FlowVersion;
use Amacraft\MarketingAutomation\Services\HealthCheck;
use Webkul\Product\Models\Product;
use Webkul\Sales\Models\Order;
use Webkul\Sales\Models\OrderAddress;
use Webkul\Sales\Models\OrderPayment;

beforeEach(function () {
    Cache::forget('marketing_automation.lag.total');
    Cache::forget('marketing_automation.lag.over_5m');
});

it('keeps the synchronous work of store triggers to at most three automation queries', function () {
    Queue::fake();

    $contact = Contact::factory()->create([
        'channel_id' => core()->getAllChannels()->first()->id,
        'email'      => 'perf@example.com',
    ]);

    $product = Product::factory()->create(['type' => 'simple']);

    $cart = Cart::factory()->create([
        'channel_id'     => $contact->channel_id,
        'customer_id'    => null,
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

    Event::dispatch('checkout.cart.add.after', $cart);

    $order = Order::factory()->create([
        'channel_id'          => $contact->channel_id,
        'customer_id'         => null,
        'customer_email'      => $contact->email,
        'base_grand_total'    => 100.00,
        'grand_total'         => 100.00,
        'base_currency_code'  => 'USD',
        'order_currency_code' => 'USD',
        'increment_id'        => 'PERF-1',
    ]);

    OrderAddress::factory()->create([
        'order_id'     => $order->id,
        'address_type' => 'order_billing',
        'email'        => $contact->email,
    ]);

    OrderPayment::factory()->create(['order_id' => $order->id]);

    DB::enableQueryLog();

    Event::dispatch('checkout.cart.add.after', $cart->refresh());
    Event::dispatch('checkout.order.save.after', $order);

    $automationQueries = collect(DB::getQueryLog())
        ->filter(fn ($entry) => preg_match('/automation_[a-z_]+/', (string) $entry['query']))
        ->count();

    DB::disableQueryLog();

    expect($automationQueries)->toBeLessThanOrEqual(3);
});

it('claims ten thousand due enrolments within the dispatch budget', function () {
    Queue::fake();

    $flow = Flow::factory()->active()->create();

    $version = FlowVersion::factory()->create(['flow_id' => $flow->id]);

    $flow->forceFill([
        'status'             => 'active',
        'activated_at'       => now(),
        'current_version_id' => $version->id,
    ])->save();

    $enrolment = Enrolment::factory()->create([
        'flow_id'         => $flow->id,
        'flow_version_id' => $version->id,
    ]);

    $flowId = $enrolment->flow_id;
    $versionId = $enrolment->flow_version_id;
    $contactId = $enrolment->contact_id;
    $channelId = $enrolment->channel_id;

    $now = now();

    $rows = [];

    for ($i = 0; $i < 10_000; $i++) {
        $rows[] = [
            'flow_id'          => $flowId,
            'flow_version_id'  => $versionId,
            'contact_id'       => $contactId,
            'channel_id'       => $channelId,
            'trigger_key'      => "perf:{$i}",
            'trigger_payload'  => '{}',
            'status'           => 'active',
            'current_step_key' => 's1',
            'next_run_at'      => $now->format('Y-m-d H:i:s'),
            'created_at'       => $now->format('Y-m-d H:i:s'),
            'updated_at'       => $now->format('Y-m-d H:i:s'),
        ];
    }

    foreach (array_chunk($rows, 1000) as $chunk) {
        DB::table('automation_enrolments')->insert($chunk);
    }

    $startedAt = microtime(true);

    $this->artisan('automation:dispatch-due')->assertSuccessful();

    $elapsed = microtime(true) - $startedAt;

    Queue::assertPushed(ProcessEnrolmentStep::class, 10_000);

    expect($elapsed)->toBeLessThan(15);
});

it('reports the dispatch lag ratio of the health check', function () {
    $flow = Flow::factory()->active()->create();

    $version = FlowVersion::factory()->create(['flow_id' => $flow->id]);

    $flow->forceFill([
        'status'             => 'active',
        'activated_at'       => now(),
        'current_version_id' => $version->id,
    ])->save();

    $onTime = Enrolment::factory()->create([
        'flow_id'         => $flow->id,
        'flow_version_id' => $version->id,
        'next_run_at'     => now(),
    ]);

    (new ProcessEnrolmentStep($onTime->id, $onTime->lock_token))->handle(app(StepRunner::class));

    expect(app(HealthCheck::class)->report()['lag_over_5m_ratio'])->toBe(0.0);

    $late = Enrolment::factory()->create([
        'flow_id'         => $flow->id,
        'flow_version_id' => $version->id,
        'next_run_at'     => now()->subMinutes(10),
    ]);

    (new ProcessEnrolmentStep($late->id, $late->lock_token))->handle(app(StepRunner::class));

    expect(app(HealthCheck::class)->report()['lag_over_5m_ratio'])->toBeGreaterThan(0.0);
});
