<?php

use Illuminate\Support\Facades\Mail;
use Webkul\CartRule\Models\CartRule;
use Webkul\Checkout\Facades\Cart;
use Webkul\Customer\Models\Customer;
use Webkul\Faker\Helpers\Product as ProductFaker;
use Amacraft\MarketingAutomation\DTOs\TriggerEvent;
use Amacraft\MarketingAutomation\Engine\EnrolmentService;
use Amacraft\MarketingAutomation\Engine\FlowValidator;
use Amacraft\MarketingAutomation\Enums\ConsentSource;
use Amacraft\MarketingAutomation\Enums\FlowType;
use Amacraft\MarketingAutomation\Enums\MessageChannel;
use Amacraft\MarketingAutomation\Models\Contact;
use Amacraft\MarketingAutomation\Models\Flow;
use Amacraft\MarketingAutomation\Models\MessageLog;
use Amacraft\MarketingAutomation\Repositories\FlowRepository;
use Amacraft\MarketingAutomation\Services\ConsentService;
use Amacraft\MarketingAutomation\Services\CouponIssuer;

use function Pest\Laravel\postJson;

beforeEach(function () {
    Mail::fake();
});

/**
 * An activated single-step marketing email flow carrying the given blocks.
 */
function couponFlow(array $blocks): Flow
{
    $flow = Flow::factory()->create(['type' => FlowType::Marketing->value]);

    $flow->update(['draft_definition' => [
        'entry' => 's1',
        'steps' => [
            's1' => [
                'type'           => 'send_email',
                'subject'        => ['en' => 'A little something'],
                'blocks'         => ['en' => $blocks],
                'validity_hours' => 24,
                'next'           => 's2',
            ],
            's2' => ['type' => 'exit'],
        ],
    ]]);

    $flow->channels()->sync([core()->getAllChannels()->first()->id]);

    app(FlowRepository::class)->activate($flow);

    return $flow->refresh();
}

/**
 * A consenting contact enrolled into the flow, so the sync queue delivers the email.
 */
function couponSend(Flow $flow, string $email): MessageLog
{
    $contact = Contact::factory()->create([
        'channel_id'  => core()->getAllChannels()->first()->id,
        'customer_id' => Customer::factory()->create(['email' => $email])->id,
        'email'       => $email,
    ]);

    app(ConsentService::class)->grant($contact, MessageChannel::Email, ConsentSource::Registration);

    app(EnrolmentService::class)->enrol(new TriggerEvent(
        type: $flow->trigger_type,
        channelId: $contact->channel_id,
        customerId: $contact->customer_id,
        email: $contact->email,
        phone: null,
        triggerKey: 'coupon:'.$contact->id,
        payload: [],
    ));

    return MessageLog::query()->where('contact_id', $contact->id)->firstOrFail();
}

it('issues a unique one-use code for each recipient of a coupon block', function () {
    $rule = CartRule::factory()->create([
        'coupon_type'         => 1,
        'use_auto_generation' => 1,
        'uses_per_coupon'     => 1,
        'usage_per_customer'  => 1,
    ]);

    $flow = couponFlow([
        ['type' => 'coupon', 'cart_rule_id' => $rule->id, 'label' => 'Your discount'],
    ]);

    $first = couponSend($flow, 'one@example.com');

    $second = couponSend($flow, 'two@example.com');

    expect($first->coupon_code)->not->toBeEmpty()
        ->and($second->coupon_code)->not->toBeEmpty()
        ->and($first->coupon_code)->not->toBe($second->coupon_code)
        ->and(str_starts_with($first->coupon_code, 'MA-'))->toBeTrue()
        ->and($first->rendered_body)->toContain($first->coupon_code);

    $coupons = DB::table('cart_rule_coupons')->where('cart_rule_id', $rule->id)->get();

    expect($coupons)->toHaveCount(2)
        ->and($coupons->pluck('usage_limit')->unique())->toContain(1)
        ->and($coupons->pluck('usage_per_customer')->unique())->toContain(1);
});

it('reuses the code of a retried message log instead of issuing a second coupon', function () {
    $rule = CartRule::factory()->create(['coupon_type' => 1, 'use_auto_generation' => 1]);

    $flow = couponFlow([
        ['type' => 'coupon', 'cart_rule_id' => $rule->id, 'label' => 'Your discount'],
    ]);

    $log = couponSend($flow, 'retry@example.com');

    $code = app(CouponIssuer::class)->issue($log, $rule->id, null);

    expect($code)->toBe($log->coupon_code)
        ->and(DB::table('cart_rule_coupons')->where('cart_rule_id', $rule->id)->count())->toBe(1);
});

it('applies the coupon block expiry days when sending', function () {
    $rule = CartRule::factory()->create(['coupon_type' => 1, 'use_auto_generation' => 1]);

    $flow = couponFlow([
        ['type' => 'coupon', 'cart_rule_id' => $rule->id, 'expires_days' => 5],
    ]);

    $log = couponSend($flow, 'expiry@example.com');

    expect($log->coupon_code)->not->toBeEmpty()
        ->and(DB::table('cart_rule_coupons')->where('code', $log->coupon_code)->value('expired_at'))
        ->toBe(now()->addDays(5)->toDateString());
});

it('caps the coupon expiry at the cart rule end date', function () {
    $ruleEnd = now()->addDays(2)->endOfDay();

    $endsSooner = CartRule::factory()->create([
        'coupon_type'         => 1,
        'use_auto_generation' => 1,
        'ends_till'           => $ruleEnd->format('Y-m-d H:i:s'),
    ]);

    $endsLater = CartRule::factory()->create([
        'coupon_type'         => 1,
        'use_auto_generation' => 1,
        'ends_till'           => now()->addMonths(3)->endOfDay()->format('Y-m-d H:i:s'),
    ]);

    $ruleEndOnly = CartRule::factory()->create([
        'coupon_type'         => 1,
        'use_auto_generation' => 1,
        'ends_till'           => $ruleEnd->format('Y-m-d H:i:s'),
    ]);

    $noEnd = CartRule::factory()->create(['coupon_type' => 1, 'use_auto_generation' => 1]);

    $issuer = app(CouponIssuer::class);

    // The rule ends before the requested days: the rule wins.
    $logA = MessageLog::factory()->create(['is_test' => false]);

    $issuer->issue($logA, $endsSooner->id, 30);

    // The rule ends after the requested days: the days win.
    $logB = MessageLog::factory()->create(['is_test' => false]);

    $issuer->issue($logB, $endsLater->id, 5);

    // No days requested: the rule end still applies.
    $logC = MessageLog::factory()->create(['is_test' => false]);

    $issuer->issue($logC, $ruleEndOnly->id, null);

    // Neither days nor rule end: the coupon never expires.
    $logD = MessageLog::factory()->create(['is_test' => false]);

    $issuer->issue($logD, $noEnd->id, null);

    expect(DB::table('cart_rule_coupons')->where('code', $logA->coupon_code)->value('expired_at'))->toBe($ruleEnd->toDateString())
        ->and(DB::table('cart_rule_coupons')->where('code', $logB->coupon_code)->value('expired_at'))->toBe(now()->addDays(5)->toDateString())
        ->and(DB::table('cart_rule_coupons')->where('code', $logC->coupon_code)->value('expired_at'))->toBe($ruleEnd->toDateString())
        ->and(DB::table('cart_rule_coupons')->where('code', $logD->coupon_code)->value('expired_at'))->toBeNull();
});

it('rejects a coupon block whose rule does not auto-generate codes', function (int $useAutoGeneration, int $couponType) {
    $rule = CartRule::factory()->create([
        'coupon_type'         => $couponType,
        'use_auto_generation' => $useAutoGeneration,
    ]);

    $flow = Flow::factory()->create(['type' => FlowType::Marketing->value]);

    $flow->channels()->sync([core()->getAllChannels()->first()->id]);

    $errors = app(FlowValidator::class)->validate($flow, [
        'entry' => 's1',
        'steps' => [
            's1' => [
                'type'    => 'send_email',
                'subject' => ['en' => 'Hi'],
                'blocks'  => ['en' => [['type' => 'coupon', 'cart_rule_id' => $rule->id, 'label' => 'x']]],
                'next'    => 's2',
            ],
            's2' => ['type' => 'exit'],
        ],
    ]);

    expect($errors['steps.s1'] ?? [])->not->toBeEmpty();
})->with([
    'manual coupons'    => [0, 1],
    'no coupons at all' => [1, 0],
]);

it('applies an issued code at checkout', function () {
    $product = (new ProductFaker)->getSimpleProductFactory()->create();

    $rule = CartRule::factory()->create([
        'coupon_type'         => 1,
        'use_auto_generation' => 1,
        'status'              => 1,
    ]);

    $log = MessageLog::factory()->create(['is_test' => false]);

    $code = app(CouponIssuer::class)->issue($log, $rule->id, null);

    postJson(route('shop.api.checkout.cart.store', [
        'product_id' => $product->id,
        'quantity'   => 1,
    ]))->assertOk();

    postJson(route('shop.api.checkout.cart.coupon.apply'), ['code' => $code])
        ->assertOk()
        ->assertJsonPath('message', trans('shop::app.checkout.coupon.success-apply'));

    expect(Cart::getCart()->coupon_code)->toBe($code);
});
