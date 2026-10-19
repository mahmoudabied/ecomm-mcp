<?php

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Webkul\Checkout\Models\Cart;
use Webkul\Checkout\Models\CartAddress;
use Webkul\Checkout\Models\CartItem;
use Webkul\Customer\Models\Customer;
use Amacraft\MarketingAutomation\Enums\AbandonedCartStatus;
use Amacraft\MarketingAutomation\Models\AbandonedCart;
use Amacraft\MarketingAutomation\Models\Contact;
use Amacraft\MarketingAutomation\Models\Enrolment;
use Amacraft\MarketingAutomation\Models\Flow;
use Amacraft\MarketingAutomation\Repositories\FlowRepository;
use Webkul\Product\Models\Product;

beforeEach(function () {
    Mail::fake();
});

/**
 * An active abandoned-cart flow bound to the default channel.
 */
function abFlow(array $overrides = []): Flow
{
    $flow = Flow::factory()->create(array_merge([
        'trigger_type'         => 'cart_abandoned',
        'trigger_config'       => ['abandon_delay_minutes' => 60],
        'reentry_window_hours' => 168,
    ], $overrides));

    $flow->update(['draft_definition' => [
        'entry' => 's1',
        'steps' => [
            's1' => [
                'type'           => 'send_email',
                'subject'        => ['en' => 'You left something behind'],
                'blocks'         => ['en' => [['type' => 'text', 'text' => 'Come back!']]],
                'validity_hours' => 24,
                'next'           => 's2',
            ],
            's2' => ['type' => 'wait', 'mode' => 'duration', 'minutes' => 1440, 'next' => 's3'],
            's3' => ['type' => 'exit'],
        ],
    ]]);

    $flow->channels()->sync([core()->getAllChannels()->first()->id]);

    app(FlowRepository::class)->activate($flow);

    return $flow->refresh();
}

/**
 * A cart with one simple item, idle for the given minutes.
 */
function abCart(array $overrides = [], int $idleMinutes = 61): Cart
{
    $product = Product::factory()->create(['type' => 'simple']);

    $cart = Cart::factory()->create(array_merge([
        'is_active'   => 1,
        'items_count' => 1,
    ], $overrides));

    CartItem::factory()->create([
        'cart_id'    => $cart->id,
        'product_id' => $product->id,
        'sku'        => $product->sku,
        'type'       => 'simple',
        'name'       => $product->name,
        'quantity'   => 1,
    ]);

    Cart::query()->where('id', $cart->id)->update([
        'updated_at' => now()->subMinutes($idleMinutes),
    ]);

    return $cart->refresh();
}

/**
 * The enrolments created for this cart, whatever other carts the database holds.
 */
function abEnrolmentsOf(Cart $cart): Builder
{
    return Enrolment::query()->where('trigger_key', 'like', 'cart:'.$cart->id.':%');
}

/**
 * Run the detection scan through the console command.
 */
function abDetect(): void
{
    Artisan::call('automation:detect-abandoned-carts');
}

it('detects an idle customer cart and enrols it into the active abandoned cart flow', function () {
    Mail::fake();

    $flow = abFlow();

    $customer = Customer::factory()->create();

    $cart = abCart(['customer_id' => $customer->id, 'customer_email' => $customer->email]);

    abDetect();

    $abandoned = AbandonedCart::query()->where('cart_id', $cart->id)->first();

    expect($abandoned)->not->toBeNull()
        ->and($abandoned->status)->toBe(AbandonedCartStatus::Abandoned)
        ->and($abandoned->contact->customer_id)->toBe($customer->id);

    $triggerKey = 'cart:'.$cart->id.':'.$cart->updated_at->getTimestamp();

    $enrolment = $flow->enrolments()->where('contact_id', $abandoned->contact_id)->first();

    expect($enrolment)->not->toBeNull()
        ->and($enrolment->trigger_key)->toBe($triggerKey);
});

it('detects a guest cart that only has an email', function () {
    abFlow();

    $cart = abCart(['customer_id' => null, 'customer_email' => 'guest@example.com']);

    abDetect();

    $abandoned = AbandonedCart::query()->where('cart_id', $cart->id)->first();

    expect($abandoned)->not->toBeNull()
        ->and($abandoned->contact->email)->toBe('guest@example.com')
        ->and($abandoned->contact->customer_id)->toBeNull()
        ->and(Enrolment::query()->where('contact_id', $abandoned->contact_id)->exists())->toBeTrue();
});

it('detects a guest cart that only has a billing phone and skips the email step', function () {
    abFlow();

    $cart = abCart(['customer_id' => null, 'customer_email' => null]);

    CartAddress::factory()->create([
        'cart_id'      => $cart->id,
        'address_type' => 'cart_billing',
        'phone'        => '+966501234567',
        'country'      => 'SA',
    ]);

    abDetect();

    $abandoned = AbandonedCart::query()->where('cart_id', $cart->id)->first();

    expect($abandoned)->not->toBeNull()
        ->and($abandoned->contact->phone_e164)->toBe('+966501234567');

    $enrolment = Enrolment::query()->where('contact_id', $abandoned->contact_id)->first();

    expect($enrolment)->not->toBeNull()
        ->and($enrolment->events()->where('event', 'skipped')->where('reason', 'invalid_contact')->exists())->toBeTrue()
        ->and($enrolment->refresh()->status->value)->toBe('active');
});

it('ignores a cart without any usable contact', function () {
    abFlow();

    $cart = abCart(['customer_id' => null, 'customer_email' => null], 90);

    Cart::query()->where('id', $cart->id)->update(['items_count' => 1]);

    abDetect();

    expect(AbandonedCart::query()->where('cart_id', $cart->id)->exists())->toBeFalse()
        ->and(abEnrolmentsOf($cart)->count())->toBe(0);
});

it('ignores a cart that has not been idle long enough', function () {
    abFlow();

    $cart = abCart(['customer_id' => null, 'customer_email' => 'recent@example.com'], 30);

    abDetect();

    expect(AbandonedCart::query()->where('cart_id', $cart->id)->exists())->toBeFalse();
});

it('does not duplicate a cart that is scanned twice', function () {
    abFlow();

    $cart = abCart(['customer_id' => null, 'customer_email' => 'twice@example.com']);

    abDetect();

    $abandoned = AbandonedCart::query()->where('cart_id', $cart->id)->first();

    $detectedAt = $abandoned->detected_at;

    abDetect();

    expect(AbandonedCart::query()->where('cart_id', $cart->id)->count())->toBe(1)
        ->and(abEnrolmentsOf($cart)->count())->toBe(1)
        ->and($abandoned->refresh()->detected_at->equalTo($detectedAt))->toBeTrue();
});

it('respects the re-entry window of the flow', function () {
    $flow = abFlow();

    $contact = Contact::factory()->create([
        'channel_id' => core()->getAllChannels()->first()->id,
        'email'      => 'returning@example.com',
    ]);

    $flow->enrolments()->create([
        'flow_version_id'  => $flow->current_version_id,
        'contact_id'       => $contact->id,
        'channel_id'       => $contact->channel_id,
        'trigger_key'      => 'cart:999:111',
        'status'           => 'completed',
        'current_step_key' => 's2',
        'next_run_at'      => null,
        'created_at'       => now()->subDays(3),
    ]);

    $cart = abCart(['customer_id' => null, 'customer_email' => 'returning@example.com']);

    abDetect();

    expect($flow->enrolments()->where('contact_id', $contact->id)->count())->toBe(1)
        ->and(AbandonedCart::query()->where('cart_id', $cart->id)->count())->toBe(1);
});

it('marks a detected cart reopened when the customer comes back and re-arms it on the next scan', function () {
    Event::fake(['marketing_automation.cart.abandoned']);

    abFlow();

    $cart = abCart(['customer_id' => null, 'customer_email' => 'reopened@example.com']);

    abDetect();

    $abandoned = AbandonedCart::query()->where('cart_id', $cart->id)->first();

    expect($abandoned->status)->toBe(AbandonedCartStatus::Abandoned);

    // The shopper returns and edits the cart.
    Cart::query()->where('id', $cart->id)->update(['updated_at' => now()]);

    Event::dispatch('checkout.cart.update.after', $cart->refresh());

    expect($abandoned->refresh()->status)->toBe(AbandonedCartStatus::Reopened);

    // The original enrolment is untouched.
    $enrolment = abEnrolmentsOf($cart)->first();

    $originalKey = $enrolment->trigger_key;

    expect($enrolment->status->value)->toBe('active');

    // The cart goes idle again at its new updated_at: a new trigger key is
    // generated, but the re-entry window keeps the shopper to one enrolment.
    Cart::query()->where('id', $cart->id)->update(['updated_at' => now()->subMinutes(90)]);

    abDetect();

    $cart = $cart->refresh();

    $newKey = 'cart:'.$cart->id.':'.$cart->updated_at->getTimestamp();

    expect($abandoned->refresh()->status)->toBe(AbandonedCartStatus::Abandoned)
        ->and($newKey)->not->toBe($originalKey)
        ->and(abEnrolmentsOf($cart)->count())->toBe(1)
        ->and(abEnrolmentsOf($cart)->first()->trigger_key)->toBe($originalKey);
});

it('captures a guest contact at checkout and returns the visitor cookie', function () {
    $cart = abCart(['customer_email' => null], 0);

    $this->withSession(['cart' => (object) ['id' => $cart->id]]);

    $this->post(route('shop.automation.checkout-contact'), [
        'email'    => 'shopper@example.com',
        'consents' => ['email' => true],
    ])
        ->assertNoContent()
        ->assertCookie('ma_contact');

    $contact = Contact::query()->where('email', 'shopper@example.com')->first();

    expect($contact)->not->toBeNull()
        ->and($contact->consents()->where('channel', 'email')->where('status', 'granted')->exists())->toBeTrue();

    expect(Cart::query()->find($cart->id)->customer_email)->toBe('shopper@example.com');
});

it('folds the guest enrolments into the customer contact on login', function () {
    $flow = abFlow();

    $guestContact = Contact::factory()->create([
        'channel_id'  => core()->getAllChannels()->first()->id,
        'email'       => 'migrated@example.com',
        'customer_id' => null,
    ]);

    $flow->enrolments()->create([
        'flow_version_id'  => $flow->current_version_id,
        'contact_id'       => $guestContact->id,
        'channel_id'       => $guestContact->channel_id,
        'trigger_key'      => 'cart:777:111',
        'status'           => 'active',
        'current_step_key' => 's2',
        'next_run_at'      => now()->addDay(),
    ]);

    $customer = Customer::factory()->create([
        'email'       => 'migrated@example.com',
        'password'    => Hash::make('password123'),
    ]);

    $this->post(route('shop.customer.session.create'), [
        'email'    => 'migrated@example.com',
        'password' => 'password123',
    ]);

    $customerContact = Contact::query()->where('customer_id', $customer->id)->first();

    expect($customerContact)->not->toBeNull()
        ->and(Contact::query()->whereNull('customer_id')->where('email', 'migrated@example.com')->exists())->toBeFalse()
        ->and($flow->enrolments()->where('contact_id', $customerContact->id)->where('status', 'active')->count())->toBe(1)
        ->and($flow->enrolments()->count())->toBe(1);
});

it('keeps the restore token of a re-armed cart while the re-entry window blocks a new enrolment', function () {
    Event::fake(['marketing_automation.cart.abandoned']);

    abFlow();

    $cart = abCart(['customer_id' => null, 'customer_email' => 'stable@example.com']);

    abDetect();

    // The shopper edits the cart, which goes idle again: a new trigger key
    // that the re-entry window refuses to enrol.
    Cart::query()->where('id', $cart->id)->update(['updated_at' => now()->subMinutes(90)]);

    Event::dispatch('checkout.cart.update.after', $cart->refresh());

    abDetect();

    $abandoned = AbandonedCart::query()->where('cart_id', $cart->id)->first();

    $tokenHash = $abandoned->restore_token_hash;

    $detectedAt = $abandoned->detected_at;

    // Later scans of the same idle state change nothing.
    $this->travel(5)->minutes();

    abDetect();

    $this->travel(5)->minutes();

    abDetect();

    $abandoned->refresh();

    expect($abandoned->restore_token_hash)->toBe($tokenHash)
        ->and($abandoned->detected_at->equalTo($detectedAt))->toBeTrue()
        ->and(Enrolment::query()->where('contact_id', $abandoned->contact_id)->count())->toBe(1);

    // Once when first detected, once when re-armed; never on the rescans.
    $dispatches = Event::dispatched(
        'marketing_automation.cart.abandoned',
        fn (string $event, array $payload) => $payload[0]->cart_id === $cart->id,
    );

    expect($dispatches)->toHaveCount(2);
});

it('enrols a flow with a longer delay on a later scan of the same idle cart', function () {
    $shortFlow = abFlow();

    $longFlow = abFlow(['trigger_config' => ['abandon_delay_minutes' => 120]]);

    $cart = abCart(['customer_id' => null, 'customer_email' => 'patient@example.com']);

    abDetect();

    $contactId = Contact::query()->where('email', 'patient@example.com')->value('id');

    expect($shortFlow->enrolments()->where('contact_id', $contactId)->count())->toBe(1)
        ->and($longFlow->enrolments()->where('contact_id', $contactId)->count())->toBe(0);

    $tokenHash = AbandonedCart::query()->where('cart_id', $cart->id)->value('restore_token_hash');

    $this->travel(60)->minutes();

    abDetect();

    expect($shortFlow->enrolments()->where('contact_id', $contactId)->count())->toBe(1)
        ->and($longFlow->enrolments()->where('contact_id', $contactId)->count())->toBe(1)
        ->and(AbandonedCart::query()->where('cart_id', $cart->id)->value('restore_token_hash'))->toBe($tokenHash);
});

it('rejects a checkout contact without an email or a phone', function () {
    $this->postJson(route('shop.automation.checkout-contact'), [
        'consents' => ['email' => true],
    ])->assertUnprocessable();

    expect(Contact::query()->count())->toBe(0);
});

it('captures the email and phone of a guest as one contact', function () {
    $cart = abCart(['customer_email' => null], 0);

    $this->withSession(['cart' => (object) ['id' => $cart->id]]);

    $this->postJson(route('shop.automation.checkout-contact'), [
        'email' => 'both@example.com',
    ])->assertNoContent();

    $this->postJson(route('shop.automation.checkout-contact'), [
        'email'    => 'both@example.com',
        'phone'    => '+966501234567',
        'consents' => ['sms' => true],
    ])->assertNoContent();

    $contact = Contact::query()->where('email', 'both@example.com')->sole();

    expect(Contact::query()->count())->toBe(1)
        ->and($contact->phone_e164)->toBe('+966501234567')
        ->and($contact->consents()->where('channel', 'sms')->where('status', 'granted')->exists())->toBeTrue();
});

it('wires the checkout contact capture to the dot-named address inputs', function () {
    $html = Blade::render("@include('marketing_automation::shop.checkout.contact-capture')\n@stack('scripts')");

    expect($html)->toContain('name="marketing_consent[email]"')
        ->and($html)->toContain("document.addEventListener('focusout'")
        ->and($html)->toContain("document.addEventListener('change'")
        ->and($html)->toContain("element.name.endsWith('.' + field)");
});
