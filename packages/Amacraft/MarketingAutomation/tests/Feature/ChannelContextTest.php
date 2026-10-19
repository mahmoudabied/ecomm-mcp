<?php

use Amacraft\MarketingAutomation\Models\AbandonedCart;
use Amacraft\MarketingAutomation\Models\Contact;
use Amacraft\MarketingAutomation\Models\Enrolment;
use Amacraft\MarketingAutomation\Models\MessageLog;
use Amacraft\MarketingAutomation\Rendering\ChannelUrl;
use Amacraft\MarketingAutomation\Rendering\ContextBuilder;
use Amacraft\MarketingAutomation\Rendering\LinkTracker;
use Amacraft\MarketingAutomation\Services\UnsubscribeLink;
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

it('builds tracking and unsubscribe links on the message channel hostname', function () {
    $channel = secondStoreChannel();

    $log = MessageLog::factory()->create([
        'channel_id'   => $channel->id,
        'is_marketing' => true,
    ]);

    $html = app(LinkTracker::class)->rewrite($log, '<a href="https://example.org/sale">Sale</a></body>');

    $host = channelScheme().'://shop2.example.com/';

    expect($html)->toContain('href="'.$host)
        ->and($html)->toContain('<img src="'.$host)
        ->and(app(UnsubscribeLink::class)->forEmail($log, 'contact-uuid'))->toStartWith($host.'automation/unsubscribe/');
});

it('keeps a scheme stored on the channel hostname', function () {
    $channel = secondStoreChannel();

    $channel->update(['hostname' => 'http://shop3.example.com/']);

    expect(app(ChannelUrl::class)->to('shop.home.index', $channel))->toBe('http://shop3.example.com/');
});
