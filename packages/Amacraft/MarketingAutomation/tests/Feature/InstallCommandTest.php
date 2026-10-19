<?php

use Illuminate\Support\Facades\Artisan;
use Webkul\Core\Models\Channel;
use Webkul\Core\Models\SubscribersList;
use Amacraft\MarketingAutomation\Enums\ConsentSource;
use Amacraft\MarketingAutomation\Enums\ConsentStatus;
use Amacraft\MarketingAutomation\Enums\MessageChannel;
use Amacraft\MarketingAutomation\Models\Consent;
use Amacraft\MarketingAutomation\Models\ConsentEvent;
use Amacraft\MarketingAutomation\Models\Contact;

function createSubscriber(string $email, int $isSubscribed): SubscribersList
{
    return SubscribersList::factory()->create([
        'email'         => $email,
        'channel_id'    => Channel::query()->first()->id,
        'is_subscribed' => $isSubscribed,
    ]);
}

it('migrates only subscribed newsletter rows into email consent', function () {
    $subscribed = createSubscriber('sub1@example.com', 1);
    createSubscriber('sub2@example.com', 1);
    createSubscriber('unsub@example.com', 0);

    Artisan::call('automation:install');

    $consents = Consent::all();

    expect(Contact::query()->where('email', 'sub1@example.com')->exists())->toBeTrue()
        ->and(Contact::query()->where('email', 'unsub@example.com')->exists())->toBeFalse()
        ->and($consents)->toHaveCount(2)
        ->and($consents->every(fn ($consent) => $consent->channel === MessageChannel::Email))->toBeTrue()
        ->and($consents->every(fn ($consent) => $consent->status === ConsentStatus::Granted))->toBeTrue()
        ->and($consents->every(fn ($consent) => $consent->source === ConsentSource::MigratedNewsletter))->toBeTrue()
        ->and(Consent::query()
            ->whereIn('channel', [MessageChannel::Sms->value, MessageChannel::Whatsapp->value])
            ->count())->toBe(0)
        ->and($subscribed->refresh()->is_subscribed)->toBe(1);
});

it('is idempotent across repeated runs', function () {
    createSubscriber('idem@example.com', 1);

    Artisan::call('automation:install');

    $eventsAfterFirstRun = ConsentEvent::count();

    expect($eventsAfterFirstRun)->toBe(1);

    Artisan::call('automation:install');

    expect(ConsentEvent::count())->toBe($eventsAfterFirstRun)
        ->and(Consent::count())->toBe(1);
});
