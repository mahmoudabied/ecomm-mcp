<?php

use Amacraft\MarketingAutomation\Enums\ConsentSource;
use Amacraft\MarketingAutomation\Enums\ConsentStatus;
use Amacraft\MarketingAutomation\Enums\MessageChannel;
use Amacraft\MarketingAutomation\Mail\AutomationMail;
use Amacraft\MarketingAutomation\Models\Consent;
use Amacraft\MarketingAutomation\Models\Contact;
use Amacraft\MarketingAutomation\Services\ConsentConfirmation;
use Amacraft\MarketingAutomation\Services\ConsentService;
use Amacraft\MarketingAutomation\Services\ContactResolver;
use Illuminate\Cookie\CookieValuePrefix;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;
use Webkul\Core\Models\Channel;
use Webkul\Core\Models\CoreConfig;
use Webkul\Customer\Models\Customer;

function setDoubleOptIn(bool $enabled): void
{
    CoreConfig::query()->updateOrCreate(
        ['code' => 'marketing_automation.consent.settings.double_opt_in'],
        ['value' => $enabled ? '1' : '0'],
    );
}

it('starts a guest checkout consent as pending and mails the confirmation link', function () {
    Mail::fake();

    $this->post(route('shop.automation.checkout-contact'), [
        'email'    => 'forge@example.com',
        'consents' => ['email' => true],
    ]);

    $contact = Contact::query()->where('email', 'forge@example.com')->first();

    $consent = Consent::query()->where('contact_id', $contact->id)->where('channel', 'email')->first();

    expect($consent->status)->toBe(ConsentStatus::Pending)
        ->and($consent->source)->toBe(ConsentSource::Checkout)
        ->and(app(ConsentService::class)->hasMarketingConsent($contact, MessageChannel::Email))->toBeFalse();

    Mail::assertSent(AutomationMail::class, fn (AutomationMail $mail) => $mail->hasTo('forge@example.com'));
});

it('grants the pending consent after the signed link is confirmed', function () {
    Mail::fake();

    $this->post(route('shop.automation.checkout-contact'), [
        'email'    => 'confirm-me@example.com',
        'consents' => ['email' => true],
    ]);

    $contact = Contact::query()->where('email', 'confirm-me@example.com')->first();

    $channel = Channel::query()->first();

    $service = app(ConsentConfirmation::class);

    $token = $service->token($contact, MessageChannel::Email);

    $this->get(route('shop.automation.consent.confirm', $token))
        ->assertOk();

    $this->post(route('shop.automation.consent.confirm', $token))
        ->assertOk();

    $consent = Consent::query()->where('contact_id', $contact->id)->where('channel', 'email')->first();

    expect($consent->refresh()->status)->toBe(ConsentStatus::Granted)
        ->and($consent->source)->toBe(ConsentSource::DoubleOptIn);
});

it('leaves the consent pending when the confirmation token is invalid', function () {
    $this->post(route('shop.automation.checkout-contact'), [
        'email'    => 'tamper@example.com',
        'consents' => ['email' => true],
    ]);

    $contact = Contact::query()->where('email', 'tamper@example.com')->first();

    $this->post(route('shop.automation.consent.confirm', 'not-a-real-token'))
        ->assertOk();

    expect(Consent::query()->where('contact_id', $contact->id)->where('channel', 'email')->first()->status)
        ->toBe(ConsentStatus::Pending);
});

it('grants directly for a logged in customer', function () {
    $customer = Customer::factory()->create();

    $this->actingAs($customer, 'customer')
        ->post(route('shop.automation.checkout-contact'), [
            'email'    => $customer->email,
            'consents' => ['email' => true],
        ]);

    $contact = Contact::query()->where('email', $customer->email)->first();

    expect(Consent::query()->where('contact_id', $contact->id)->where('channel', 'email')->first()->status)
        ->toBe(ConsentStatus::Granted);
});

it('grants directly for guests when double opt-in is disabled', function () {
    setDoubleOptIn(false);

    $this->post(route('shop.automation.checkout-contact'), [
        'email'    => 'direct@example.com',
        'consents' => ['email' => true],
    ]);

    $contact = Contact::query()->where('email', 'direct@example.com')->first();

    expect(Consent::query()->where('contact_id', $contact->id)->where('channel', 'email')->first()->status)
        ->toBe(ConsentStatus::Granted);
});

it('does not graft a phone onto an email-matched contact for a guest', function () {
    $existing = app(ContactResolver::class)->resolve(
        channelId: (int) Channel::query()->first()->id,
        customerId: null,
        email: 'shared@example.com',
    );

    $this->post(route('shop.automation.checkout-contact'), [
        'email' => 'shared@example.com',
        'phone' => '+201001234567',
    ]);

    $existing->refresh();

    $isolated = Contact::query()->where('phone_e164', '+201001234567')->first();

    expect($existing->phone_e164)->toBeNull()
        ->and($isolated)->not->toBeNull()
        ->and($isolated->id)->not->toBe($existing->id)
        ->and(Contact::query()->where('email', 'shared@example.com')->count())->toBe(2);
});

it('reuses the matched contact when the guest identity matches exactly', function () {
    $channelId = (int) Channel::query()->first()->id;

    $existing = app(ContactResolver::class)->resolve(
        channelId: $channelId,
        customerId: null,
        email: 'exact@example.com',
    );

    $this->post(route('shop.automation.checkout-contact'), [
        'email' => 'exact@example.com',
    ]);

    expect(Contact::query()->where('email', 'exact@example.com')->count())->toBe(1)
        ->and($existing->refresh()->phone_e164)->toBeNull();
});

it('never hands out the visitor cookie of a matched contact to a guest', function () {
    $channelId = (int) Channel::query()->first()->id;

    app(ContactResolver::class)->resolve(
        channelId: $channelId,
        customerId: null,
        email: 'victim@example.com',
    );

    $matched = $this->post(route('shop.automation.checkout-contact'), [
        'email' => 'victim@example.com',
    ]);

    expect(collect($matched->headers->getCookies())->contains(fn ($cookie) => $cookie->getName() === 'ma_contact'))->toBeFalse();

    $created = $this->post(route('shop.automation.checkout-contact'), [
        'email' => 'fresh-cookie@example.com',
    ]);

    $cookie = collect($created->headers->getCookies())->first(fn ($cookie) => $cookie->getName() === 'ma_contact');

    $decrypted = CookieValuePrefix::remove(Crypt::decryptString($cookie->getValue()));

    expect($cookie)->not->toBeNull()
        ->and($decrypted)->toBe(Contact::query()->where('email', 'fresh-cookie@example.com')->first()->uuid);
});
