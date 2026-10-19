<?php

use Illuminate\Support\Facades\Mail;
use Webkul\Customer\Models\Customer;
use Amacraft\MarketingAutomation\DTOs\TriggerEvent;
use Amacraft\MarketingAutomation\Engine\EnrolmentService;
use Amacraft\MarketingAutomation\Enums\ConsentSource;
use Amacraft\MarketingAutomation\Enums\ConsentStatus;
use Amacraft\MarketingAutomation\Enums\FlowType;
use Amacraft\MarketingAutomation\Enums\MessageChannel;
use Amacraft\MarketingAutomation\Mail\AutomationMail;
use Amacraft\MarketingAutomation\Models\ConsentEvent;
use Amacraft\MarketingAutomation\Models\Contact;
use Amacraft\MarketingAutomation\Models\Flow;
use Amacraft\MarketingAutomation\Models\MessageEvent;
use Amacraft\MarketingAutomation\Models\MessageLog;
use Amacraft\MarketingAutomation\Repositories\FlowRepository;
use Amacraft\MarketingAutomation\Services\ConsentService;
use Amacraft\MarketingAutomation\Services\SignedToken;

beforeEach(function () {
    Mail::fake();

    core()->getAllChannels()->first()->forceFill(['timezone' => 'UTC'])->save();
});

/**
 * A single-step email flow of the given type, activated on the channel.
 */
function unsubFlow(FlowType $type): Flow
{
    $flow = Flow::factory()->create(['type' => $type->value]);

    $flow->update(['draft_definition' => [
        'entry' => 's1',
        'steps' => [
            's1' => [
                'type'           => 'send_email',
                'subject'        => ['en' => 'Hello'],
                'blocks'         => ['en' => [['type' => 'text', 'text' => 'Body']]],
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
 * A contact with email consent, enrolled into the flow.
 */
function unsubSend(Flow $flow, FlowType $type): array
{
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
        customerId: $contact->customer_id,
        email: $contact->email,
        phone: null,
        triggerKey: 'test:'.$contact->id,
        payload: [],
    ));

    $log = MessageLog::query()->where('contact_id', $contact->id)->first();

    return [$contact, $log];
}

it('carries the one-click unsubscribe url and headers on marketing email', function () {
    $flow = unsubFlow(FlowType::Marketing);

    [$contact, $log] = unsubSend($flow, FlowType::Marketing);

    expect($log)->not->toBeNull()
        ->and($log->is_marketing)->toBeTrue();

    Mail::assertSent(AutomationMail::class, function ($mail) {
        $headers = $mail->build()->headers();

        expect($headers->text['List-Unsubscribe'])->toContain(route('shop.automation.unsubscribe', ''))
            ->and($headers->text['List-Unsubscribe-Post'])->toBe('List-Unsubscribe=One-Click');

        return true;
    });
});

it('shows a confirm page before revoking', function () {
    $flow = unsubFlow(FlowType::Marketing);

    [$contact, $log] = unsubSend($flow, FlowType::Marketing);

    $token = app(SignedToken::class)->make($contact->uuid, 'email', (string) $log->uuid);

    $this->get(route('shop.automation.unsubscribe', $token))
        ->assertOk()
        ->assertSee(trans('marketing_automation::app.shop.unsubscribe.confirm-text', ['channel' => 'email']), escape: false);

    // Only the confirmation page so far: the consent is untouched.
    expect($contact->consents()->where('channel', 'email')->first()->refresh()->status)
        ->toBe(ConsentStatus::Granted);
});

it('revokes on the confirm post and records the message event', function () {
    $flow = unsubFlow(FlowType::Marketing);

    [$contact, $log] = unsubSend($flow, FlowType::Marketing);

    $token = app(SignedToken::class)->make($contact->uuid, 'email', (string) $log->uuid);

    $this->post(route('shop.automation.unsubscribe.confirm', $token))->assertOk();

    $consent = $contact->consents()->where('channel', 'email')->first();

    $event = ConsentEvent::query()
        ->where('contact_id', $contact->id)
        ->where('channel', 'email')
        ->where('source', 'unsubscribe_link')
        ->latest('created_at')
        ->first();

    expect($consent->refresh()->status)->toBe(ConsentStatus::Revoked)
        ->and($event)->not->toBeNull()
        ->and($event->created_at)->not->toBeNull();

    // The message that carried the link is marked unsubscribed.
    expect(MessageEvent::query()
        ->where('message_log_id', $log->id)
        ->where('type', 'unsubscribed')
        ->exists())->toBeTrue();
});

it('accepts an RFC 8058 one-click post without csrf', function () {
    $flow = unsubFlow(FlowType::Marketing);

    [$contact, $log] = unsubSend($flow, FlowType::Marketing);

    $token = app(SignedToken::class)->make($contact->uuid, 'email', (string) $log->uuid);

    $this->post(route('shop.automation.unsubscribe.confirm', $token), [
        'List-Unsubscribe' => 'One-Click',
    ])
        ->assertOk()
        ->assertContent('');

    expect($contact->consents()->where('channel', 'email')->first()->refresh()->status)
        ->toBe(ConsentStatus::Revoked);
});

it('rejects a tampered token', function () {
    $flow = unsubFlow(FlowType::Marketing);

    [$contact, $log] = unsubSend($flow, FlowType::Marketing);

    $token = app(SignedToken::class)->make($contact->uuid, 'email', (string) $log->uuid);

    $this->get(route('shop.automation.unsubscribe', $token.'x'))->assertNotFound();

    $this->post(route('shop.automation.unsubscribe.confirm', $token.'x'))->assertNotFound();

    expect($contact->consents()->where('channel', 'email')->first()->refresh()->status)
        ->toBe(ConsentStatus::Granted);
});

it('never adds the unsubscribe header to transactional email', function () {
    $flow = unsubFlow(FlowType::Transactional);

    [$contact, $log] = unsubSend($flow, FlowType::Transactional);

    expect($log)->not->toBeNull()
        ->and($log->is_marketing)->toBeFalse();

    Mail::assertSent(AutomationMail::class, function ($mail) {
        $headers = $mail->build()->headers();

        expect($headers->text)->toBeEmpty();

        return true;
    });
});
