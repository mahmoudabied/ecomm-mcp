<?php

use Illuminate\Support\Facades\Mail;
use Webkul\Customer\Models\Customer;
use Webkul\MarketingAutomation\Enums\ConsentSource;
use Webkul\MarketingAutomation\Enums\ConsentStatus;
use Webkul\MarketingAutomation\Enums\MessageChannel;
use Webkul\MarketingAutomation\Models\ConsentEvent;
use Webkul\MarketingAutomation\Models\Contact;
use Webkul\MarketingAutomation\Services\ConsentService;
use Webkul\MarketingAutomation\Services\ContactResolver;

function optContactFor(Customer $customer): Contact
{
    return app(ContactResolver::class)->forCustomer($customer, (int) $customer->channel_id);
}

it('grants the ticked channel at registration with the registration source', function () {
    Mail::fake();

    $this->post(route('shop.customers.register.store'), [
        'first_name'            => 'Sara',
        'last_name'             => 'Ahmed',
        'email'                 => 'optin@example.com',
        'password'              => 'secret123',
        'password_confirmation' => 'secret123',
        'marketing_consent'     => ['sms' => '1'],
    ]);

    $customer = Customer::query()->where('email', 'optin@example.com')->first();

    $contact = Contact::query()->where('email', 'optin@example.com')->first();

    $consent = $contact->consents()->where('channel', 'sms')->first();

    expect($consent->status)->toBe(ConsentStatus::Granted)
        ->and(ConsentEvent::query()
            ->where('contact_id', $contact->id)
            ->where('channel', 'sms')
            ->where('source', 'registration')
            ->where('status', 'granted')
            ->exists())->toBeTrue()
        ->and($contact->consents()->where('channel', 'email')->exists())->toBeFalse();
});

it('grants nothing when no checkbox was ticked', function () {
    Mail::fake();

    $this->post(route('shop.customers.register.store'), [
        'first_name'            => 'Lina',
        'last_name'             => 'Adel',
        'email'                 => 'quiet@example.com',
        'password'              => 'secret123',
        'password_confirmation' => 'secret123',
    ]);

    $customer = Customer::query()->where('email', 'quiet@example.com')->first();

    $contact = optContactFor($customer);

    expect($contact->consents()->count())->toBe(0);
});

it('revokes a channel from the account preferences page', function () {
    $customer = Customer::factory()->create();

    $contact = optContactFor($customer);

    app(ConsentService::class)->grant(
        $contact,
        MessageChannel::Whatsapp,
        ConsentSource::Registration,
    );

    $this->actingAs($customer, 'customer')
        ->put(route('shop.customers.account.marketing-preferences.update'), [
            'consents' => ['whatsapp' => false],
        ]);

    $consent = $contact->consents()->where('channel', 'whatsapp')->first();

    expect($consent->refresh()->status)->toBe(ConsentStatus::Revoked)
        ->and(ConsentEvent::query()
            ->where('contact_id', $contact->id)
            ->where('channel', 'whatsapp')
            ->where('source', 'account')
            ->where('status', 'revoked')
            ->exists())->toBeTrue();
});

it('grants the checkout capture consents with the checkout source', function () {
    $this->post(route('shop.automation.checkout-contact'), [
        'email'    => 'checkout-optin@example.com',
        'consents' => ['email' => true, 'sms' => true],
    ]);

    $contact = Contact::query()->where('email', 'checkout-optin@example.com')->first();

    expect($contact->consents()->where('channel', 'email')->where('status', 'granted')->exists())->toBeTrue()
        ->and($contact->consents()->where('channel', 'sms')->where('status', 'granted')->exists())->toBeTrue()
        ->and($contact->consents()->get()->pluck('status')->unique())->toContain(ConsentStatus::Granted)
        ->and(ConsentEvent::query()->where('contact_id', $contact->id)->where('source', 'checkout')->exists())->toBeTrue();
});

it('shows the three unchecked opt-in boxes on the signup page', function () {
    $this->get(route('shop.customers.register.index'))
        ->assertOk()
        ->assertSee('name="marketing_consent[email]"', escape: false)
        ->assertSee('name="marketing_consent[sms]"', escape: false)
        ->assertSee('name="marketing_consent[whatsapp]"', escape: false);
});
