<?php

use Amacraft\MarketingAutomation\Enums\ConsentSource;
use Amacraft\MarketingAutomation\Enums\ConsentStatus;
use Amacraft\MarketingAutomation\Enums\MessageChannel;
use Amacraft\MarketingAutomation\Models\Contact;
use Amacraft\MarketingAutomation\Models\Enrolment;
use Amacraft\MarketingAutomation\Services\ConsentService;
use Amacraft\MarketingAutomation\Services\ContactResolver;
use Illuminate\Support\Facades\Event;
use Webkul\Core\Models\Channel;
use Webkul\Core\Models\SubscribersList;
use Webkul\Customer\Models\Customer;

function makeContactResolver(): ContactResolver
{
    return app(ContactResolver::class);
}

function firstChannelId(): int
{
    return Channel::query()->first()->id;
}

it('creates and then re-finds a contact by customer id', function () {
    $customer = Customer::factory()->create(['email' => 'buyer@example.com']);
    $resolver = makeContactResolver();

    $created = $resolver->resolve(firstChannelId(), $customer->id, $customer->email);
    $found = $resolver->resolve(firstChannelId(), $customer->id, null);

    expect($created->id)->toBe($found->id);
});

it('finds the same contact by case-insensitive email', function () {
    $resolver = makeContactResolver();

    $first = $resolver->resolve(firstChannelId(), null, 'Guest@Example.COM');
    $second = $resolver->resolve(firstChannelId(), null, 'guest@example.com');

    expect($first->id)->toBe($second->id)
        ->and($second->email)->toBe('guest@example.com');
});

it('finds the same contact by phone regardless of input format', function () {
    $resolver = makeContactResolver();

    $first = $resolver->resolve(firstChannelId(), null, null, '0501234567', 'SA');
    $second = $resolver->resolve(firstChannelId(), null, null, '+966501234567', 'SA');

    expect($first->id)->toBe($second->id)
        ->and($second->phone_e164)->toBe('+966501234567');
});

it('pulls identity fields from the customer row when customer id is given', function () {
    $customer = Customer::factory()->create([
        'email'      => 'pulled@example.com',
        'first_name' => 'Sara',
    ]);

    $contact = makeContactResolver()->resolve(firstChannelId(), $customer->id);

    expect($contact->email)->toBe('pulled@example.com')
        ->and($contact->first_name)->toBe('Sara');
});

it('merges a guest contact into the customer contact keeping the latest consent', function () {
    $customer = Customer::factory()->create(['email' => 'merge@example.com']);
    $resolver = makeContactResolver();

    $guest = $resolver->resolve(firstChannelId(), null, 'guest@example.com', '0501234567', 'SA');
    $customerContact = $resolver->forCustomer($customer, firstChannelId());

    $consentService = app(ConsentService::class);
    $consentService->grant($customerContact, MessageChannel::Email, ConsentSource::Registration);

    $this->travelTo(now()->addHour());
    $consentService->revoke($guest, MessageChannel::Email, ConsentSource::Account);

    $merged = $resolver->merge($guest, $customerContact);

    expect(Contact::find($guest->id))->toBeNull()
        ->and($merged->id)->toBe($customerContact->id)
        ->and($consentService->status($merged, MessageChannel::Email))->toBe(ConsentStatus::Revoked);
});

it('moves enrolments to the customer contact and cancels duplicates', function () {
    $customer = Customer::factory()->create();
    $resolver = makeContactResolver();

    $guest = $resolver->resolve(firstChannelId(), null, 'dup@example.com');
    $customerContact = $resolver->forCustomer($customer, firstChannelId());

    $customerEnrolment = Enrolment::factory()->create([
        'contact_id'  => $customerContact->id,
        'channel_id'  => firstChannelId(),
        'trigger_key' => 'cart:2:456',
    ]);
    $guestEnrolment = Enrolment::factory()->create([
        'contact_id'      => $guest->id,
        'channel_id'      => firstChannelId(),
        'trigger_key'     => 'cart:2:456',
        'flow_id'         => $customerEnrolment->flow_id,
        'flow_version_id' => $customerEnrolment->flow_version_id,
    ]);

    $resolver->merge($guest, $customerContact);

    expect(Enrolment::find($guestEnrolment->id))->toBeNull()
        ->and(Enrolment::query()
            ->where('contact_id', $customerContact->id)
            ->where('flow_id', $customerEnrolment->flow_id)
            ->count())->toBe(1)
        ->and($customerEnrolment->refresh()->contact_id)->toBe($customerContact->id);
});

it('records two events and dispatches twice for grant then revoke', function () {
    Event::fake(['marketing_automation.consent.changed']);

    $contact = makeContactResolver()->resolve(firstChannelId(), null, 'consent@example.com');
    $service = app(ConsentService::class);

    $service->grant($contact, MessageChannel::Sms, ConsentSource::Checkout);
    $service->revoke($contact, MessageChannel::Sms, ConsentSource::SmsStop);

    expect($service->status($contact, MessageChannel::Sms))->toBe(ConsentStatus::Revoked)
        ->and($contact->consentEvents()->count())->toBe(2);

    Event::assertDispatchedTimes('marketing_automation.consent.changed', 2);
});

it('does not dispatch when the consent status is unchanged', function () {
    Event::fake(['marketing_automation.consent.changed']);

    $contact = makeContactResolver()->resolve(firstChannelId(), null, 'same@example.com');
    $service = app(ConsentService::class);

    $service->grant($contact, MessageChannel::Email, ConsentSource::Checkout);
    $service->grant($contact, MessageChannel::Email, ConsentSource::Account);

    expect($contact->consentEvents()->count())->toBe(2);

    Event::assertDispatchedTimes('marketing_automation.consent.changed', 1);
});

it('unsubscribes the newsletter row when email consent is revoked', function () {
    $contact = makeContactResolver()->resolve(firstChannelId(), null, 'news@example.com');

    $subscriber = SubscribersList::factory()->create([
        'email'         => 'news@example.com',
        'channel_id'    => firstChannelId(),
        'is_subscribed' => 1,
    ]);

    app(ConsentService::class)->revoke($contact, MessageChannel::Email, ConsentSource::UnsubscribeLink);

    expect((bool) $subscriber->refresh()->is_subscribed)->toBeFalse();
});
