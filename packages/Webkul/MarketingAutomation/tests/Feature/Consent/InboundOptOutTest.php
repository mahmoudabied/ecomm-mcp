<?php

use Webkul\MarketingAutomation\Enums\ConsentSource;
use Webkul\MarketingAutomation\Enums\ConsentStatus;
use Webkul\MarketingAutomation\Enums\MessageChannel;
use Webkul\MarketingAutomation\Models\ConsentEvent;
use Webkul\MarketingAutomation\Models\Contact;
use Webkul\MarketingAutomation\Models\ProviderConnection;
use Webkul\MarketingAutomation\Services\ConsentService;

/**
 * The Twilio request signature the driver verifies.
 */
function optOutTwilioSignature(string $url, array $params, string $authToken): string
{
    ksort($params);

    $data = $url;

    foreach ($params as $key => $value) {
        $data .= $key.$value;
    }

    return base64_encode(hash_hmac('sha1', $data, $authToken, true));
}

function optOutSmsContact(): Contact
{
    return Contact::factory()->create([
        'channel_id' => core()->getDefaultChannel()->id,
        'phone_e164' => '+966501234567',
    ]);
}

function optOutMetaConnection(): ProviderConnection
{
    return ProviderConnection::factory()->whatsapp()->create([
        'driver'      => 'meta_cloud',
        'credentials' => [
            'access_token'    => 'mtok',
            'phone_number_id' => 'PN1',
            'waba_id'         => '1111111111',
            'app_secret'      => 'msecret',
        ],
    ]);
}

it('revokes sms consent on a signed twilio STOP inbound', function () {
    $connection = ProviderConnection::factory()->sms()->create([
        'driver'      => 'twilio',
        'credentials' => ['account_sid' => 'ACtest1234567890', 'auth_token' => 'twilio-token'],
    ]);

    $contact = optOutSmsContact();

    app(ConsentService::class)->grant(
        $contact,
        MessageChannel::Sms,
        ConsentSource::Registration,
    );

    $url = route('automation.webhooks.receive', [$connection->driver, $connection->uuid]);

    $params = ['MessageSid' => 'SMstop1', 'From' => '+966501234567', 'Body' => 'STOP'];

    $this->post($url, $params, ['X-Twilio-Signature' => optOutTwilioSignature($url, $params, 'twilio-token')])
        ->assertOk();

    $consent = $contact->consents()->where('channel', 'sms')->first();

    expect($consent->refresh()->status)->toBe(ConsentStatus::Revoked)
        ->and(ConsentEvent::query()
            ->where('contact_id', $contact->id)
            ->where('channel', 'sms')
            ->where('source', 'sms_stop')
            ->exists())->toBeTrue();
});

it('revokes sms consent on the arabic stop keyword', function () {
    $connection = ProviderConnection::factory()->sms()->create([
        'driver'      => 'twilio',
        'credentials' => ['account_sid' => 'ACtest1234567890', 'auth_token' => 'twilio-token'],
    ]);

    $contact = optOutSmsContact();

    app(ConsentService::class)->grant(
        $contact,
        MessageChannel::Sms,
        ConsentSource::Registration,
    );

    $url = route('automation.webhooks.receive', [$connection->driver, $connection->uuid]);

    $params = ['MessageSid' => 'SMstop2', 'From' => '+966501234567', 'Body' => 'إلغاء'];

    $this->post($url, $params, ['X-Twilio-Signature' => optOutTwilioSignature($url, $params, 'twilio-token')])
        ->assertOk();

    expect($contact->consents()->where('channel', 'sms')->first()->refresh()->status)
        ->toBe(ConsentStatus::Revoked);
});

it('revokes whatsapp consent on a meta STOP quick reply', function () {
    $connection = optOutMetaConnection();

    $contact = optOutSmsContact();

    app(ConsentService::class)->grant(
        $contact,
        MessageChannel::Whatsapp,
        ConsentSource::Registration,
    );

    $body = json_encode([
        'entry' => [
            ['changes' => [
                ['value' => ['messages' => [
                    ['from' => '966501234567', 'button' => ['text' => 'STOP']],
                ]]],
            ]],
        ],
    ]);

    $signature = 'sha256='.hash_hmac('sha256', $body, 'msecret');

    $this->call('POST', route('automation.webhooks.receive', [$connection->driver, $connection->uuid]), [], [], [], [
        'HTTP_X-Hub-Signature-256' => $signature,
        'CONTENT_TYPE'             => 'application/json',
    ], $body)->assertOk();

    $consent = $contact->consents()->where('channel', 'whatsapp')->first();

    expect($consent->refresh()->status)->toBe(ConsentStatus::Revoked)
        ->and(ConsentEvent::query()
            ->where('contact_id', $contact->id)
            ->where('channel', 'whatsapp')
            ->where('source', 'whatsapp_optout')
            ->exists())->toBeTrue();
});

it('rejects an inbound with a bad signature', function () {
    $connection = ProviderConnection::factory()->sms()->create([
        'driver'      => 'twilio',
        'credentials' => ['account_sid' => 'ACtest1234567890', 'auth_token' => 'twilio-token'],
    ]);

    $contact = optOutSmsContact();

    app(ConsentService::class)->grant(
        $contact,
        MessageChannel::Sms,
        ConsentSource::Registration,
    );

    $this->post(route('automation.webhooks.receive', [$connection->driver, $connection->uuid]), [
        'MessageSid' => 'SMstop3',
        'From'       => '+966501234567',
        'Body'       => 'STOP',
    ], ['X-Twilio-Signature' => 'forged'])->assertForbidden();

    expect($contact->consents()->where('channel', 'sms')->first()->refresh()->status)
        ->toBe(ConsentStatus::Granted);
});

it('returns 404 for an unknown connection uuid', function () {
    $this->post(route('automation.webhooks.receive', ['twilio', 'no-such-uuid']), [
        'Body' => 'STOP',
    ])->assertNotFound();
});

it('leaves the consent alone on a normal inbound reply', function () {
    $connection = ProviderConnection::factory()->sms()->create([
        'driver'      => 'twilio',
        'credentials' => ['account_sid' => 'ACtest1234567890', 'auth_token' => 'twilio-token'],
    ]);

    $contact = optOutSmsContact();

    app(ConsentService::class)->grant(
        $contact,
        MessageChannel::Sms,
        ConsentSource::Registration,
    );

    $url = route('automation.webhooks.receive', [$connection->driver, $connection->uuid]);

    $params = ['MessageSid' => 'SMhello', 'From' => '+966501234567', 'Body' => 'Hello, tell me more'];

    $this->post($url, $params, ['X-Twilio-Signature' => optOutTwilioSignature($url, $params, 'twilio-token')])
        ->assertOk();

    expect($contact->consents()->where('channel', 'sms')->first()->refresh()->status)
        ->toBe(ConsentStatus::Granted);
});
