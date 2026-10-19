<?php

use Amacraft\MarketingAutomation\Models\AbandonedCart;
use Amacraft\MarketingAutomation\Models\Contact;
use Amacraft\MarketingAutomation\Models\Enrolment;
use Amacraft\MarketingAutomation\Rendering\ContextBuilder;
use Webkul\Core\Models\Channel;

function secondStoreChannel(): Channel
{
    $channel = Channel::factory()->create([
        'hostname' => 'shop2.example.com',
    ]);

    $translation = $channel->translations()->firstOrNew(['locale' => 'en']);

    $translation->name = 'Second Store';

    $translation->save();

    return $channel->refresh();
}

/**
 * The scheme ChannelUrl derives from the app URL.
 */
function channelScheme(): string
{
    return str_starts_with((string) config('app.url'), 'http://') ? 'http' : 'https';
}

it('builds the store context from the message channel, not the current one', function () {
    $channel = secondStoreChannel();

    $context = app(ContextBuilder::class)->forSample($channel->id, 'en');

    expect($context['store']['name'])->toBe('Second Store')
        ->and($context['store']['url'])->toStartWith(channelScheme().'://shop2.example.com');
});

it('builds restore and tracking links on the message channel hostname', function () {
    $channel = secondStoreChannel();

    $contact = Contact::factory()->create([
        'channel_id' => $channel->id,
        'email'      => 'second-store@example.com',
    ]);

    $enrolment = Enrolment::factory()->create([
        'channel_id'      => $channel->id,
        'contact_id'      => $contact->id,
        'trigger_payload' => ['cart_id' => null],
    ]);

    $cartAbandoned = AbandonedCart::factory()->create([
        'channel_id' => $channel->id,
        'contact_id' => $contact->id,
    ]);

    $enrolment->update(['trigger_payload' => [
        'cart_id'           => $cartAbandoned->cart_id,
        'abandoned_cart_id' => $cartAbandoned->id,
    ]]);

    $context = app(ContextBuilder::class)->forEnrolment($enrolment->refresh());

    expect($context['store']['name'])->toBe('Second Store')
        ->and($context['store']['url'])->toStartWith(channelScheme().'://shop2.example.com')
        ->and($context['cart']['restore_url'] ?? '')->toStartWith(channelScheme().'://shop2.example.com/automation/cart/restore/');
});
