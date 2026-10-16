<?php

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Webkul\Attribute\Models\Attribute;
use Webkul\Checkout\Models\Cart;
use Webkul\Checkout\Models\CartItem;
use Webkul\Customer\Models\Customer;
use Webkul\Faker\Helpers\Product as ProductFaker;
use Webkul\MarketingAutomation\Console\Commands\DispatchDueCommand;
use Webkul\MarketingAutomation\DTOs\TriggerEvent;
use Webkul\MarketingAutomation\Engine\EnrolmentService;
use Webkul\MarketingAutomation\Engine\ExitGoalService;
use Webkul\MarketingAutomation\Enums\AbandonedCartStatus;
use Webkul\MarketingAutomation\Enums\ConsentSource;
use Webkul\MarketingAutomation\Enums\JourneyEvent;
use Webkul\MarketingAutomation\Enums\MessageChannel;
use Webkul\MarketingAutomation\Enums\TriggerType;
use Webkul\MarketingAutomation\Models\AbandonedCart;
use Webkul\MarketingAutomation\Models\Contact;
use Webkul\MarketingAutomation\Models\Enrolment;
use Webkul\MarketingAutomation\Models\Flow;
use Webkul\MarketingAutomation\Models\MessageLog;
use Webkul\MarketingAutomation\Repositories\FlowRepository;
use Webkul\MarketingAutomation\Services\AbandonedCartService;
use Webkul\MarketingAutomation\Services\ConsentService;
use Webkul\MarketingAutomation\Services\RestoreToken;
use Webkul\Product\Models\Product;
use Webkul\Product\Models\ProductAttributeValue;
use Webkul\Sales\Models\Order;
use Webkul\Sales\Models\OrderAddress;
use Webkul\Sales\Models\OrderPayment;

/**
 * The abandoned-cart recovery flow with an email step that shows the cart.
 */
function recFlow(): Flow
{
    $flow = Flow::factory()->create([
        'trigger_type'         => 'cart_abandoned',
        'trigger_config'       => ['abandon_delay_minutes' => 60],
        'reentry_window_hours' => 168,
        'exit_goals'           => [['type' => 'order_placed']],
    ]);

    $flow->update(['draft_definition' => [
        'entry' => 's1',
        'steps' => [
            's1' => [
                'type'           => 'send_email',
                'subject'        => ['en' => 'You left something behind'],
                'blocks'         => ['en' => [
                    ['type' => 'text', 'text' => 'Your cart total is {{ cart.total }}.'],
                    ['type' => 'cart_items'],
                    ['type' => 'button', 'label' => 'Restore cart', 'href' => '{{ cart.restore_url }}'],
                ]],
                'validity_hours' => 24,
                'next'           => 's2',
            ],
            's2' => ['type' => 'wait', 'mode' => 'duration', 'minutes' => 1380, 'next' => 's3'],
            's3' => [
                'type'           => 'send_email',
                'subject'        => ['en' => 'Still thinking it over?'],
                'blocks'         => ['en' => [['type' => 'text', 'text' => 'Last chance!']]],
                'validity_hours' => 24,
                'next'           => 's4',
            ],
            's4' => ['type' => 'exit'],
        ],
    ]]);

    $flow->channels()->sync([core()->getAllChannels()->first()->id]);

    app(FlowRepository::class)->activate($flow);

    return $flow->refresh();
}

/**
 * A saleable simple product, built the way the core test suites build one.
 */
function recProduct(): Product
{
    return (new ProductFaker)->getSimpleProductFactory()->create();
}

/**
 * Disable a product through its status attribute, which the saleable check reads.
 */
function recDisableProduct(Product $product): void
{
    ProductAttributeValue::query()
        ->where('product_id', $product->id)
        ->where('attribute_id', Attribute::query()->where('code', 'status')->value('id'))
        ->update(['boolean_value' => 0]);
}

/**
 * A guest cart with one saleable item and a known email.
 */
function recCart(string $email = 'shopper@example.com', ?int $customerId = null): Cart
{
    $product = recProduct();

    $cart = Cart::factory()->create([
        'is_active'        => 1,
        'items_count'      => 1,
        'customer_id'      => $customerId,
        'customer_email'   => $customerId ? null : $email,
        'grand_total'      => 100.00,
        'base_grand_total' => 100.00,
    ]);

    CartItem::factory()->create([
        'cart_id'    => $cart->id,
        'product_id' => $product->id,
        'sku'        => $product->sku,
        'type'       => 'simple',
        'name'       => 'Recursion Widget',
        'quantity'   => 2,
        'price'      => 50.00,
        'base_price' => 50.00,
    ]);

    Cart::query()->where('id', $cart->id)->update(['updated_at' => now()->subMinutes(61)]);

    return $cart->refresh();
}

/**
 * Detect the cart through the real service and return the abandoned record.
 */
function recDetect(Cart $cart): AbandonedCart
{
    app(AbandonedCartService::class)->detect();

    return AbandonedCart::query()->where('cart_id', $cart->id)->first();
}

/**
 * An order that carries the payment relation the core order listeners expect.
 */
function orderWithPayment(array $attributes = []): Order
{
    return Order::factory()
        ->has(OrderPayment::factory()->state(['method' => 'cashondelivery']), 'payment')
        ->create($attributes);
}

/**
 * Enrol a contact through the engine exactly as detection would.
 */
function recEnrol(Flow $flow, AbandonedCart $abandoned): Enrolment
{
    $cart = Cart::query()->find($abandoned->cart_id);

    app(EnrolmentService::class)->enrol(new TriggerEvent(
        type: TriggerType::CartAbandoned,
        channelId: $abandoned->channel_id,
        customerId: $abandoned->contact->customer_id,
        email: $abandoned->contact->email,
        phone: $abandoned->contact->phone_e164,
        triggerKey: 'cart:'.$cart->id.':'.$cart->updated_at->getTimestamp(),
        payload: [
            'cart_id'           => $cart->id,
            'abandoned_cart_id' => $abandoned->id,
            'idle_minutes'      => 61,
        ],
    ));

    return $flow->enrolments()->where('contact_id', $abandoned->contact_id)->first();
}

it('restores a guest cart from a valid token', function () {
    $cart = recCart();

    $abandoned = recDetect($cart);

    $token = app(RestoreToken::class)->for($abandoned);

    $this->get(route('shop.automation.cart.restore', $token))
        ->assertRedirect(route('shop.checkout.cart.index'));

    expect(session('cart')->id)->toBe($cart->id)
        ->and($cart->refresh()->is_active)->toBe(1);
});

it('rejects an expired or unknown token with a warning', function () {
    $cart = recCart();

    $abandoned = recDetect($cart);

    $token = app(RestoreToken::class)->for($abandoned);

    AbandonedCart::query()->where('id', $abandoned->id)->update([
        'restore_token_expires_at' => now()->subDay(),
    ]);

    $this->get(route('shop.automation.cart.restore', $token))
        ->assertRedirect(route('shop.checkout.cart.index'))
        ->assertSessionHas('warning', trans('marketing_automation::app.shop.restore.expired'));

    $this->get(route('shop.automation.cart.restore', 'not-a-real-token'))
        ->assertRedirect(route('shop.checkout.cart.index'))
        ->assertSessionHas('warning');
});

it('reactivates the cart of a logged-in customer', function () {
    $customer = Customer::factory()->create();

    $cart = recCart(customerId: $customer->id);

    $abandoned = recDetect($cart);

    $token = app(RestoreToken::class)->for($abandoned);

    $this->actingAs($customer, 'customer')
        ->get(route('shop.automation.cart.restore', $token))
        ->assertRedirect(route('shop.checkout.cart.index'));

    expect($cart->refresh()->is_active)->toBe(1)
        ->and($abandoned->refresh()->restored_cart_id)->toBeNull();
});

it('copies a customer cart into a guest cart without a login redirect', function () {
    $customer = Customer::factory()->create();

    $cart = recCart(customerId: $customer->id);

    $abandoned = recDetect($cart);

    $token = app(RestoreToken::class)->for($abandoned);

    $this->get(route('shop.automation.cart.restore', $token))
        ->assertRedirect(route('shop.checkout.cart.index'));

    $restoredCartId = $abandoned->refresh()->restored_cart_id;

    expect($restoredCartId)->not->toBeNull()
        ->and($restoredCartId)->not->toBe($cart->id)
        ->and(session('cart')->id)->toBe($restoredCartId)
        ->and($cart->refresh()->is_active)->toBe(0);

    $restored = Cart::query()->find($restoredCartId);

    expect($restored->items->count())->toBe(1)
        ->and((int) $restored->items->first()->quantity)->toBe(2)
        ->and($restored->customer_id)->toBeNull();
});

it('recovers the original abandoned cart when an order is placed from the restored cart', function () {
    $customer = Customer::factory()->create();

    $cart = recCart(customerId: $customer->id);

    $abandoned = recDetect($cart);

    $token = app(RestoreToken::class)->for($abandoned);

    $this->get(route('shop.automation.cart.restore', $token));

    $restoredCartId = $abandoned->refresh()->restored_cart_id;

    $order = orderWithPayment([
        'cart_id'        => $restoredCartId,
        'customer_id'    => $customer->id,
        'customer_email' => $customer->email,
    ]);

    Event::dispatch('checkout.order.save.after', $order);

    expect($abandoned->refresh()->status)->toBe(AbandonedCartStatus::Recovered)
        ->and($abandoned->recovered_order_id)->toBe($order->id);

    // The original cart is inactive, so later scans leave it alone.
    $count = AbandonedCart::query()->where('cart_id', $cart->id)->count();

    app(AbandonedCartService::class)->detect();

    expect(AbandonedCart::query()->where('cart_id', $cart->id)->count())->toBe($count);
});

it('never stores the plain restore token, only its hash', function () {
    $cart = recCart();

    $abandoned = recDetect($cart);

    $token = app(RestoreToken::class)->for($abandoned);

    $abandoned->refresh();

    // Every raw column value of the record.
    foreach ($abandoned->getAttributes() as $value) {
        expect((string) $value)->not->toContain($token);
    }

    // Every raw trigger payload of the enrolments.
    foreach (Enrolment::query()->get() as $enrolment) {
        expect(json_encode($enrolment->trigger_payload))->not->toContain($token);
    }

    expect($abandoned->restore_token_hash)->toBe(app(RestoreToken::class)->hash($token));
});

it('marks the abandoned cart recovered and the enrolment converted when the order is placed', function () {
    Mail::fake();

    $flow = recFlow();

    $customer = Customer::factory()->create();

    $contact = Contact::factory()->create([
        'channel_id'  => core()->getAllChannels()->first()->id,
        'customer_id' => $customer->id,
        'email'       => $customer->email,
    ]);

    app(ConsentService::class)->grant($contact, MessageChannel::Email, ConsentSource::Registration);

    $cart = recCart(customerId: $customer->id);

    $abandoned = recDetect($cart);

    $abandoned->update(['contact_id' => $contact->id]);

    $enrolment = recEnrol($flow, $abandoned);

    // The first email has gone out; the flow waits for the second one.
    expect($enrolment->refresh()->current_step_key)->toBe('s3')
        ->and($enrolment->next_run_at->greaterThan(now()->addHours(22)))->toBeTrue()
        ->and(MessageLog::query()->where('enrolment_id', $enrolment->id)->count())->toBe(1);

    $order = orderWithPayment([
        'cart_id'        => $cart->id,
        'customer_id'    => $customer->id,
        'customer_email' => $customer->email,
    ]);

    Event::dispatch('checkout.order.save.after', $order);

    expect($abandoned->refresh()->status)->toBe(AbandonedCartStatus::Recovered)
        ->and($abandoned->recovered_order_id)->toBe($order->id)
        ->and($enrolment->refresh()->status->value)->toBe('converted')
        ->and($enrolment->events()->where('event', 'exited')->where('reason', 'goal_met')->exists())->toBeTrue();

    // The second step never fires after the exit goal was met.
    $this->travelTo(now()->addHours(24), fn () => $this->artisan(DispatchDueCommand::class, ['--now' => true]));

    expect(MessageLog::query()->where('enrolment_id', $enrolment->id)->count())->toBe(1);
});

it('renders the cart items, total and restore link in the recovery email', function () {
    Mail::fake();

    $flow = recFlow();

    $contact = Contact::factory()->create([
        'channel_id' => core()->getAllChannels()->first()->id,
        'email'      => 'shopper@example.com',
    ]);

    app(ConsentService::class)->grant($contact, MessageChannel::Email, ConsentSource::Registration);

    $cart = recCart();

    $abandoned = recDetect($cart);

    $abandoned->update(['contact_id' => $contact->id]);

    recEnrol($flow, $abandoned);

    $log = MessageLog::query()->where('contact_id', $contact->id)->where('message_channel', 'email')->first();

    $item = $cart->items->first();

    expect($log)->not->toBeNull()
        ->and($log->rendered_body)->toContain($item->name)
        ->and($log->rendered_body)->toContain(core()->formatPrice(100.00, $cart->cart_currency_code));
    preg_match('/automation\/cart\/restore\/([A-Za-z0-9_\-.]+)/', $log->rendered_body, $matches);

    expect($matches)->not->toBeEmpty()
        ->and($matches[1])->toBe(app(RestoreToken::class)->for($abandoned->refresh()));
});

it('hides unavailable products from the email and skips the step when the cart went empty', function () {
    Mail::fake();

    $flow = recFlow();

    $contact = Contact::factory()->create([
        'channel_id' => core()->getAllChannels()->first()->id,
        'email'      => 'shopper@example.com',
    ]);

    app(ConsentService::class)->grant($contact, MessageChannel::Email, ConsentSource::Registration);

    $cart = recCart();

    // A second, still-sellable item so the cart does not go empty.
    $secondProduct = recProduct();

    CartItem::factory()->create([
        'cart_id'    => $cart->id,
        'product_id' => $secondProduct->id,
        'sku'        => $secondProduct->sku,
        'type'       => 'simple',
        'name'       => 'Second Widget',
        'quantity'   => 1,
    ]);

    // One item disabled after enrolment: the email renders without it.
    recDisableProduct($cart->items->first()->product);

    $abandoned = AbandonedCart::factory()->create([
        'cart_id'    => $cart->id,
        'contact_id' => $contact->id,
        'channel_id' => $contact->channel_id,
    ]);

    recEnrol($flow, $abandoned);

    $log = MessageLog::query()->where('contact_id', $contact->id)->where('message_channel', 'email')->first();

    expect($log)->not->toBeNull()
        ->and($log->rendered_body)->not->toContain($cart->items->first()->name);

    // Every item disabled: the step is skipped as cart_empty.
    Mail::fake();

    $secondFlow = recFlow();

    $contact2 = Contact::factory()->create([
        'channel_id' => core()->getAllChannels()->first()->id,
        'email'      => 'empty@example.com',
    ]);

    app(ConsentService::class)->grant($contact2, MessageChannel::Email, ConsentSource::Registration);

    $cart2 = recCart('empty@example.com');

    foreach ($cart2->items as $item) {
        recDisableProduct($item->product);
    }

    $abandoned2 = AbandonedCart::factory()->create([
        'cart_id'    => $cart2->id,
        'contact_id' => $contact2->id,
        'channel_id' => $contact2->channel_id,
    ]);

    $enrolment = recEnrol($secondFlow, $abandoned2);

    expect($enrolment->events()->where('event', JourneyEvent::Skipped->value)->where('reason', 'cart_empty')->exists())->toBeTrue()
        ->and(MessageLog::query()->where('contact_id', $contact2->id)->count())->toBe(0);
});

it('refuses the restore link once the cart was ordered', function () {
    $cart = recCart();

    $abandoned = recDetect($cart);

    $token = app(RestoreToken::class)->for($abandoned);

    $order = orderWithPayment(['cart_id' => $cart->id, 'customer_email' => 'shopper@example.com']);

    Cart::query()->where('id', $cart->id)->update(['is_active' => 0]);

    Event::dispatch('checkout.order.save.after', $order);

    expect($abandoned->refresh()->status)->toBe(AbandonedCartStatus::Recovered);

    $this->get(route('shop.automation.cart.restore', $token))
        ->assertRedirect(route('shop.checkout.cart.index'))
        ->assertSessionHas('warning', trans('marketing_automation::app.shop.restore.expired'));

    expect($cart->refresh()->is_active)->toBe(0);
});

it('leaves another customer\'s cart untouched when a logged-in customer opens the link', function () {
    $owner = Customer::factory()->create();

    $visitor = Customer::factory()->create();

    $cart = recCart(customerId: $owner->id);

    $abandoned = recDetect($cart);

    $token = app(RestoreToken::class)->for($abandoned);

    Cart::query()->where('id', $cart->id)->update(['is_active' => 0]);

    $this->actingAs($visitor, 'customer')
        ->get(route('shop.automation.cart.restore', $token))
        ->assertRedirect(route('shop.checkout.cart.index'));

    expect($cart->refresh()->is_active)->toBe(0)
        ->and((int) $cart->customer_id)->toBe($owner->id)
        ->and($abandoned->refresh()->restored_cart_id)->toBeNull();
});

it('tags the restore link with the message and records the click', function () {
    Mail::fake();

    $flow = recFlow();

    $contact = Contact::factory()->create([
        'channel_id' => core()->getAllChannels()->first()->id,
        'email'      => 'clicker@example.com',
    ]);

    app(ConsentService::class)->grant($contact, MessageChannel::Email, ConsentSource::Registration);

    $cart = recCart('clicker@example.com');

    $abandoned = recDetect($cart);

    $abandoned->update(['contact_id' => $contact->id]);

    recEnrol($flow, $abandoned);

    $log = MessageLog::query()->where('contact_id', $contact->id)->where('message_channel', 'email')->sole();

    preg_match('/automation\/cart\/restore\/[A-Za-z0-9_\-.]+\?m=([0-9a-f\-]+)/', html_entity_decode($log->rendered_body), $matches);

    expect($matches[1] ?? null)->toBe((string) $log->uuid);

    $this->get(route('shop.automation.cart.restore', app(RestoreToken::class)->for($abandoned->refresh())).'?m='.$log->uuid)
        ->assertRedirect(route('shop.checkout.cart.index'));

    expect($log->events()->where('type', 'clicked')->exists())->toBeTrue();
});

it('converts the enrolment of an unmerged guest contact when the customer orders', function () {
    $flow = recFlow();

    $customer = Customer::factory()->create(['email' => 'guestfirst@example.com']);

    $guest = Contact::factory()->create([
        'channel_id'  => core()->getAllChannels()->first()->id,
        'customer_id' => null,
        'email'       => 'guestfirst@example.com',
    ]);

    $enrolment = recExitEnrolment($flow, $guest);

    $order = orderWithPayment([
        'customer_id'    => $customer->id,
        'customer_email' => 'GuestFirst@example.com',
    ]);

    app(ExitGoalService::class)->orderPlaced($order);

    expect($enrolment->refresh()->status->value)->toBe('converted');
});

it('converts the enrolment of a phone-only guest when the order carries that phone', function () {
    $flow = recFlow();

    $guest = Contact::factory()->create([
        'channel_id' => core()->getAllChannels()->first()->id,
        'email'      => null,
        'phone_e164' => '+966501234567',
    ]);

    $enrolment = recExitEnrolment($flow, $guest);

    $order = orderWithPayment(['customer_id' => null, 'customer_email' => 'other@example.com']);

    OrderAddress::factory()->create([
        'order_id'   => $order->id,
        'first_name' => 'Phone',
        'last_name'  => 'Only',
        'address'    => 'Street 1',
        'city'       => 'Riyadh',
        'country'    => 'SA',
        'postcode'   => '12345',
        'phone'      => '0501234567',
    ]);

    app(ExitGoalService::class)->orderPlaced($order->refresh());

    expect($enrolment->refresh()->status->value)->toBe('converted');
});

/**
 * An active enrolment created before the order, for the exit goal tests.
 */
function recExitEnrolment(Flow $flow, Contact $contact): Enrolment
{
    return $flow->enrolments()->create([
        'flow_version_id'  => $flow->current_version_id,
        'contact_id'       => $contact->id,
        'channel_id'       => $contact->channel_id,
        'trigger_key'      => 'cart:exit:'.$contact->id,
        'status'           => 'active',
        'current_step_key' => 's3',
        'next_run_at'      => now()->addDay(),
        'created_at'       => now()->subHour(),
    ]);
}
