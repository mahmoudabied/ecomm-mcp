<?php

use Webkul\Checkout\Models\Cart;
use Webkul\Checkout\Models\CartItem;
use Webkul\Customer\Models\Customer;
use Amacraft\MarketingAutomation\Engine\ConditionEvaluator;
use Amacraft\MarketingAutomation\Enums\ConsentSource;
use Amacraft\MarketingAutomation\Enums\MessageChannel;
use Amacraft\MarketingAutomation\Models\Contact;
use Amacraft\MarketingAutomation\Models\Enrolment;
use Amacraft\MarketingAutomation\Services\ConsentService;
use Webkul\Product\Models\Product;
use Webkul\Sales\Models\Order;
use Webkul\Sales\Models\OrderItem;

function makeEnrolmentFor(Customer $customer, array $payload = []): Enrolment
{
    $contact = Contact::factory()->create([
        'channel_id'  => core()->getAllChannels()->first()->id,
        'customer_id' => $customer->id,
        'email'       => $customer->email,
        'locale'      => 'en',
    ]);

    return Enrolment::factory()->create([
        'contact_id'      => $contact->id,
        'channel_id'      => $contact->channel_id,
        'trigger_payload' => $payload,
    ]);
}

it('detects orders placed since enrolment', function () {
    $customer = Customer::factory()->create();

    $enrolment = makeEnrolmentFor($customer);

    $rule = ['all' => [['field' => 'has_ordered_since_enrolment', 'op' => 'is_false']]];

    expect(app(ConditionEvaluator::class)->evaluate($rule, $enrolment))->toBeTrue();

    Order::factory()->create([
        'customer_id'    => $customer->id,
        'customer_email' => $customer->email,
        'created_at'     => now(),
    ]);

    expect(app(ConditionEvaluator::class)->evaluate($rule, $enrolment))->toBeFalse();
});

it('ignores orders that predate the enrolment', function () {
    $customer = Customer::factory()->create();

    $enrolment = makeEnrolmentFor($customer);

    Order::factory()->create([
        'customer_id'    => $customer->id,
        'customer_email' => $customer->email,
        'created_at'     => now()->subDays(3),
    ]);

    $rule = ['all' => [['field' => 'has_ordered_since_enrolment', 'op' => 'is_false']]];

    expect(app(ConditionEvaluator::class)->evaluate($rule, $enrolment))->toBeTrue();
});

it('counts orders and sums the lifetime spend of non-canceled orders', function () {
    $customer = Customer::factory()->create();

    $enrolment = makeEnrolmentFor($customer);

    Order::factory()->create(['customer_id' => $customer->id, 'customer_email' => $customer->email, 'base_grand_total' => 100.00]);
    Order::factory()->create(['customer_id' => $customer->id, 'customer_email' => $customer->email, 'base_grand_total' => 50.00]);
    Order::factory()->create(['customer_id' => $customer->id, 'customer_email' => $customer->email, 'base_grand_total' => 999.00, 'status' => 'canceled']);

    expect(app(ConditionEvaluator::class)->evaluate(['all' => [['field' => 'order_count', 'op' => 'eq', 'value' => 2]]], $enrolment))->toBeTrue()
        ->and(app(ConditionEvaluator::class)->evaluate(['all' => [['field' => 'lifetime_spend', 'op' => 'gte', 'value' => 150]]], $enrolment))->toBeTrue()
        ->and(app(ConditionEvaluator::class)->evaluate(['all' => [['field' => 'last_order_total', 'op' => 'gt', 'value' => 49]]], $enrolment))->toBeTrue();
});

it('reads customer, contact and channel attributes', function () {
    $customer = Customer::factory()->create(['customer_group_id' => 1]);

    $enrolment = makeEnrolmentFor($customer);

    expect(app(ConditionEvaluator::class)->evaluate(['all' => [['field' => 'customer_group_id', 'op' => 'eq', 'value' => 1]]], $enrolment))->toBeTrue()
        ->and(app(ConditionEvaluator::class)->evaluate(['all' => [['field' => 'locale', 'op' => 'eq', 'value' => 'en']]], $enrolment))->toBeTrue()
        ->and(app(ConditionEvaluator::class)->evaluate(['all' => [['field' => 'channel_id', 'op' => 'eq', 'value' => $enrolment->channel_id]]], $enrolment))->toBeTrue();
});

it('checks consent through the consent service', function () {
    $customer = Customer::factory()->create();

    $enrolment = makeEnrolmentFor($customer);

    $granted = ['all' => [['field' => 'has_consent.sms', 'op' => 'is_true']]];

    expect(app(ConditionEvaluator::class)->evaluate($granted, $enrolment))->toBeFalse();

    app(ConsentService::class)->grant(
        $enrolment->contact,
        MessageChannel::Sms,
        ConsentSource::Registration,
    );

    expect(app(ConditionEvaluator::class)->evaluate($granted, $enrolment))->toBeTrue();
});

it('reads the cart value of the trigger cart', function () {
    $customer = Customer::factory()->create();

    $cart = Cart::factory()->create([
        'customer_id'      => $customer->id,
        'is_active'        => 1,
        'base_grand_total' => 75.50,
    ]);

    $enrolment = makeEnrolmentFor($customer, ['cart_id' => $cart->id]);

    expect(app(ConditionEvaluator::class)->evaluate(['all' => [['field' => 'cart_value', 'op' => 'gte', 'value' => 75]]], $enrolment))->toBeTrue()
        ->and(app(ConditionEvaluator::class)->evaluate(['all' => [['field' => 'cart_value', 'op' => 'gt', 'value' => 100]]], $enrolment))->toBeFalse();
});

it('matches products and categories in the trigger cart', function () {
    $customer = Customer::factory()->create();

    $product = Product::factory()->create(['type' => 'simple']);

    $cart = Cart::factory()->create([
        'customer_id' => $customer->id,
        'is_active'   => 1,
    ]);

    CartItem::factory()->create([
        'cart_id'    => $cart->id,
        'product_id' => $product->id,
        'quantity'   => 1,
        'sku'        => $product->sku,
        'type'       => 'simple',
        'name'       => $product->name,
    ]);

    $enrolment = makeEnrolmentFor($customer, ['cart_id' => $cart->id]);

    $containsProduct = ['all' => [['field' => 'cart_contains_product', 'op' => 'is_true', 'value' => $product->id]]];

    expect(app(ConditionEvaluator::class)->evaluate($containsProduct, $enrolment))->toBeTrue()
        ->and(app(ConditionEvaluator::class)->evaluate(['all' => [['field' => 'cart_contains_product', 'op' => 'is_true', 'value' => $product->id + 5000]]], $enrolment))->toBeFalse();
});

it('matches products in the trigger order', function () {
    $customer = Customer::factory()->create();

    $product = Product::factory()->create(['type' => 'simple']);

    $order = Order::factory()->create([
        'customer_id'    => $customer->id,
        'customer_email' => $customer->email,
    ]);

    OrderItem::factory()->create([
        'order_id'   => $order->id,
        'product_id' => $product->id,
        'sku'        => $product->sku,
        'type'       => 'simple',
        'name'       => $product->name,
    ]);

    $enrolment = makeEnrolmentFor($customer, ['order_id' => $order->id]);

    expect(app(ConditionEvaluator::class)->evaluate(['all' => [['field' => 'order_contains_product', 'op' => 'is_true', 'value' => $product->id]]], $enrolment))->toBeTrue();
});

it('evaluates any-of rules with nesting', function () {
    $customer = Customer::factory()->create();

    $enrolment = makeEnrolmentFor($customer);

    $rule = [
        'any' => [
            ['all' => [['field' => 'order_count', 'op' => 'gte', 'value' => 3]]],
            ['all' => [['field' => 'locale', 'op' => 'eq', 'value' => 'en']]],
        ],
    ];

    expect(app(ConditionEvaluator::class)->evaluate($rule, $enrolment))->toBeTrue();

    $all = [
        'all' => [
            ['field' => 'order_count', 'op' => 'gte', 'value' => 3],
            ['field' => 'locale', 'op' => 'eq', 'value' => 'en'],
        ],
    ];

    expect(app(ConditionEvaluator::class)->evaluate($all, $enrolment))->toBeFalse();
});

it('matches no orders for a contact with neither an email nor a customer', function () {
    $contact = Contact::factory()->create([
        'channel_id'  => core()->getAllChannels()->first()->id,
        'customer_id' => null,
        'email'       => null,
        'phone_e164'  => '+966501234567',
    ]);

    $enrolment = Enrolment::factory()->create([
        'contact_id' => $contact->id,
        'channel_id' => $contact->channel_id,
    ]);

    // Somebody else's guest order must not leak into this contact's history.
    Order::factory()->create([
        'customer_id'    => null,
        'customer_email' => 'guest@example.com',
        'created_at'     => now(),
    ]);

    $evaluator = app(ConditionEvaluator::class);

    expect($evaluator->evaluate(['all' => [['field' => 'order_count', 'op' => 'eq', 'value' => 0]]], $enrolment))->toBeTrue()
        ->and($evaluator->evaluate(['all' => [['field' => 'has_ordered_since_enrolment', 'op' => 'is_false']]], $enrolment))->toBeTrue()
        ->and($evaluator->evaluate(['all' => [['field' => 'lifetime_spend', 'op' => 'eq', 'value' => 0]]], $enrolment))->toBeTrue();
});
