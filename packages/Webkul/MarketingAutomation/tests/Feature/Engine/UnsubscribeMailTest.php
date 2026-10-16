<?php

use Illuminate\Support\Collection;
use Webkul\Customer\Models\Customer;
use Webkul\MarketingAutomation\DTOs\TriggerEvent;
use Webkul\MarketingAutomation\Engine\EnrolmentService;
use Webkul\MarketingAutomation\Enums\ConsentSource;
use Webkul\MarketingAutomation\Enums\ConsentStatus;
use Webkul\MarketingAutomation\Enums\FlowType;
use Webkul\MarketingAutomation\Enums\MessageChannel;
use Webkul\MarketingAutomation\Enums\MessageEventType;
use Webkul\MarketingAutomation\Enums\TriggerType;
use Webkul\MarketingAutomation\Models\Contact;
use Webkul\MarketingAutomation\Models\Flow;
use Webkul\MarketingAutomation\Models\MessageEvent;
use Webkul\MarketingAutomation\Models\MessageLog;
use Webkul\MarketingAutomation\Repositories\FlowRepository;
use Webkul\MarketingAutomation\Services\ConsentService;
use Webkul\MarketingAutomation\Services\SignedToken;

beforeEach(function () {
    core()->getAllChannels()->first()->forceFill(['timezone' => 'UTC'])->save();

    config(['mail.default' => 'array']);

    sentAutomationMessages()->splice(0);
});

function sentAutomationMessages(): Collection
{
    return app('mail.manager')->mailer('array')->getSymfonyTransport()->messages();
}

function sendSingleEmailFlow(FlowType $type, array $blocks = [['type' => 'text', 'text' => 'Hello']]): Contact
{
    $flow = Flow::factory()->create(['type' => $type->value]);

    $flow->update(['draft_definition' => [
        'entry' => 's1',
        'steps' => [
            's1' => ['type' => 'send_email', 'subject' => ['en' => 'Hi'], 'blocks' => ['en' => $blocks], 'validity_hours' => 24, 'next' => 's2'],
            's2' => ['type' => 'exit'],
        ],
    ]]);

    $flow->channels()->sync([core()->getAllChannels()->first()->id]);

    app(FlowRepository::class)->activate($flow);

    $customer = Customer::factory()->create();

    $contact = Contact::factory()->create([
        'channel_id'  => core()->getAllChannels()->first()->id,
        'customer_id' => $customer->id,
        'email'       => $customer->email,
    ]);

    app(ConsentService::class)->grant($contact, MessageChannel::Email, ConsentSource::Registration);

    app(EnrolmentService::class)->enrol(new TriggerEvent(
        type: $flow->trigger_type,
        channelId: $contact->channel_id,
        customerId: $customer->id,
        email: $customer->email,
        phone: null,
        triggerKey: 'unsub:'.$contact->id,
        payload: [],
    ));

    return $contact;
}

it('sends a marketing email through a real mailer with one-click unsubscribe headers', function () {
    $contact = sendSingleEmailFlow(FlowType::Marketing);

    $log = MessageLog::query()->where('contact_id', $contact->id)->sole();

    expect($log->status->value)->toBe('sent')
        ->and($log->error_code)->not->toBe('mail_error')
        ->and(sentAutomationMessages())->toHaveCount(1);

    $headers = sentAutomationMessages()->first()->getOriginalMessage()->getHeaders();

    $unsubscribeUrl = route('shop.automation.unsubscribe', app(SignedToken::class)->make($contact->uuid, 'email', $log->uuid));

    expect($headers->get('List-Unsubscribe')?->getBodyAsString())->toBe('<'.$unsubscribeUrl.'>')
        ->and($headers->get('List-Unsubscribe-Post')?->getBodyAsString())->toBe('List-Unsubscribe=One-Click')
        ->and($log->rendered_body)->toContain(e($unsubscribeUrl));
});

it('renders the unsubscribe link inside a template footer block', function () {
    $contact = sendSingleEmailFlow(FlowType::Marketing, [['type' => 'footer', 'text' => 'Thanks']]);

    $log = MessageLog::query()->where('contact_id', $contact->id)->sole();

    expect($log->rendered_body)->toContain(route('shop.automation.unsubscribe', ''))
        ->and($log->rendered_body)->toContain('Thanks');
});

it('sends transactional emails without an unsubscribe header or link', function () {
    $contact = sendSingleEmailFlow(FlowType::Transactional);

    $log = MessageLog::query()->where('contact_id', $contact->id)->sole();

    $headers = sentAutomationMessages()->first()->getOriginalMessage()->getHeaders();

    expect($log->status->value)->toBe('sent')
        ->and($headers->has('List-Unsubscribe'))->toBeFalse()
        ->and($headers->has('List-Unsubscribe-Post'))->toBeFalse()
        ->and($log->rendered_body)->not->toContain('automation/unsubscribe');
});

it('revokes email consent from a one-click unsubscribe POST', function () {
    $contact = sendSingleEmailFlow(FlowType::Marketing);

    $log = MessageLog::query()->where('contact_id', $contact->id)->sole();

    $token = app(SignedToken::class)->make($contact->uuid, 'email', $log->uuid);

    $this->get(route('shop.automation.unsubscribe', $token))->assertOk();

    expect(app(ConsentService::class)->status($contact, MessageChannel::Email))->toBe(ConsentStatus::Granted);

    $this->post(route('shop.automation.unsubscribe.confirm', $token), ['List-Unsubscribe' => 'One-Click'])
        ->assertOk()
        ->assertContent('');

    expect(app(ConsentService::class)->status($contact, MessageChannel::Email))->toBe(ConsentStatus::Revoked)
        ->and($contact->consents()->where('channel', 'email')->first()->source)->toBe(ConsentSource::UnsubscribeLink)
        ->and(MessageEvent::query()->where('message_log_id', $log->id)->where('type', MessageEventType::Unsubscribed->value)->exists())->toBeTrue();
});

it('rejects a tampered unsubscribe token', function () {
    $token = app(SignedToken::class)->make('some-uuid', 'email', 'message-uuid');

    $this->get(route('shop.automation.unsubscribe', $token.'x'))->assertNotFound();

    $this->post(route('shop.automation.unsubscribe.confirm', 'bm90LWEtdG9rZW4.abc'))->assertNotFound();
});

it('does not create a contact when no flow matches the event', function () {
    $customer = Customer::factory()->create();

    app(EnrolmentService::class)->enrol(new TriggerEvent(
        type: TriggerType::CustomerBirthday,
        channelId: core()->getAllChannels()->first()->id,
        customerId: $customer->id,
        email: $customer->email,
        phone: null,
        triggerKey: 'none:'.$customer->id,
        payload: [],
    ));

    expect(Contact::query()->where('customer_id', $customer->id)->exists())->toBeFalse();
});
