<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Webkul\Core\Models\Channel;
use Amacraft\MarketingAutomation\DTOs\InboundMessage;
use Amacraft\MarketingAutomation\Enums\ConsentSource;
use Amacraft\MarketingAutomation\Enums\ConsentStatus;
use Amacraft\MarketingAutomation\Enums\MessageChannel;
use Amacraft\MarketingAutomation\Enums\MessageStatus;
use Amacraft\MarketingAutomation\Enums\WhatsAppTemplateStatus;
use Amacraft\MarketingAutomation\Jobs\SyncWhatsAppTemplates;
use Amacraft\MarketingAutomation\Models\Consent;
use Amacraft\MarketingAutomation\Models\Contact;
use Amacraft\MarketingAutomation\Models\MessageEvent;
use Amacraft\MarketingAutomation\Models\MessageLog;
use Amacraft\MarketingAutomation\Models\ProviderConnection;
use Amacraft\MarketingAutomation\Models\WhatsAppTemplate;
use Amacraft\MarketingAutomation\Repositories\ProviderConnectionRepository;
use Amacraft\MarketingAutomation\Services\ConsentService;
use Amacraft\MarketingAutomation\Services\ProviderManager;
use Amacraft\MarketingAutomation\Services\Providers\MessageBirdSmsProvider;
use Amacraft\MarketingAutomation\Services\Providers\MetaCloudWhatsAppProvider;
use Amacraft\MarketingAutomation\Services\Providers\TwilioWhatsAppProvider;
use Amacraft\MarketingAutomation\Services\Providers\VonageSmsProvider;
use Amacraft\MarketingAutomation\Services\WebhookProcessor;

function webhookUrl(ProviderConnection $connection, string $action = 'receive'): string
{
    return $action === 'verify'
        ? route('automation.webhooks.verify', [$connection->driver, $connection->uuid])
        : route('automation.webhooks.receive', [$connection->driver, $connection->uuid]);
}

function smsMessageLog(ProviderConnection $connection, string $providerMessageId): MessageLog
{
    return MessageLog::factory()->create([
        'provider_connection_id' => $connection->id,
        'provider_message_id'    => $providerMessageId,
        'message_channel'        => MessageChannel::Sms->value,
        'status'                 => 'sent',
    ]);
}

function twilioSignature(string $url, array $params, string $authToken): string
{
    ksort($params);

    $data = $url;

    foreach ($params as $key => $value) {
        $data .= $key.$value;
    }

    return base64_encode(hash_hmac('sha1', $data, $authToken, true));
}

it('accepts a Twilio webhook with a valid signature', function () {
    $this->loginAsAdmin();

    $connection = ProviderConnection::factory()->sms()->create([
        'driver'      => 'twilio',
        'credentials' => ['account_sid' => 'ACtest1234567890', 'auth_token' => 'twilio-token'],
    ]);

    $log = smsMessageLog($connection, 'SMtwilio123');

    $params = ['MessageSid' => 'SMtwilio123', 'MessageStatus' => 'delivered'];

    $signature = twilioSignature(webhookUrl($connection), $params, 'twilio-token');

    $this->post(webhookUrl($connection), $params, ['X-Twilio-Signature' => $signature])
        ->assertOk();

    expect($log->refresh()->status->value)->toBe('delivered')
        ->and($log->delivered_at)->not->toBeNull()
        ->and(MessageEvent::query()->where('message_log_id', $log->id)->where('type', 'delivered')->exists())->toBeTrue();
});

it('rejects a Twilio webhook with a bad signature', function () {
    $connection = ProviderConnection::factory()->sms()->create([
        'driver'      => 'twilio',
        'credentials' => ['account_sid' => 'ACtest1234567890', 'auth_token' => 'twilio-token'],
    ]);

    $log = smsMessageLog($connection, 'SMtwilio456');

    $this->post(webhookUrl($connection), [
        'MessageSid'    => 'SMtwilio456',
        'MessageStatus' => 'delivered',
    ], ['X-Twilio-Signature' => 'invalid-signature'])
        ->assertForbidden();

    expect($log->refresh()->status->value)->toBe('sent');
});

it('accepts a Meta webhook with a valid hub signature', function () {
    $credentials = ['access_token' => 'mtok', 'phone_number_id' => 'PN1', 'waba_id' => '1111111111', 'app_secret' => 'msecret'];

    $connection = ProviderConnection::factory()->whatsapp()->create([
        'driver'      => 'meta_cloud',
        'credentials' => $credentials,
    ]);

    $log = MessageLog::factory()->create([
        'provider_connection_id' => $connection->id,
        'provider_message_id'    => 'wamid.meta123',
        'message_channel'        => MessageChannel::Whatsapp->value,
        'status'                 => 'sent',
    ]);

    $body = json_encode([
        'entry' => [
            ['changes' => [
                ['value' => ['statuses' => [
                    ['id' => 'wamid.meta123', 'status' => 'delivered'],
                ]]],
            ]],
        ],
    ]);

    $signature = 'sha256='.hash_hmac('sha256', $body, 'msecret');

    $this->call('POST', webhookUrl($connection), [], [], [], ['HTTP_X-Hub-Signature-256' => $signature, 'CONTENT_TYPE' => 'application/json'], $body)
        ->assertOk();

    expect($log->refresh()->status->value)->toBe('delivered')
        ->and(MessageEvent::query()->where('message_log_id', $log->id)->where('type', 'delivered')->exists())->toBeTrue();
});

it('rejects a Meta webhook with a wrong signature', function () {
    $connection = ProviderConnection::factory()->whatsapp()->create([
        'driver'      => 'meta_cloud',
        'credentials' => ['access_token' => 'mtok', 'phone_number_id' => 'PN1', 'waba_id' => '1111111111', 'app_secret' => 'msecret'],
    ]);

    $this->call('POST', webhookUrl($connection), [], [], [], ['HTTP_X-Hub-Signature-256' => 'sha256=deadbeef', 'CONTENT_TYPE' => 'application/json'], '{"entry":[]}')
        ->assertForbidden();
});

it('answers the Meta hub verify handshake', function () {
    $connection = ProviderConnection::factory()->whatsapp()->create([
        'driver'      => 'meta_cloud',
        'credentials' => ['access_token' => 'mtok', 'phone_number_id' => 'PN1', 'waba_id' => '1111111111', 'app_secret' => 'msecret'],
    ]);

    $this->get(webhookUrl($connection, 'verify').'?hub_verify_token='.$connection->webhook_secret.'&hub_challenge=ch4113ng3')
        ->assertOk()
        ->assertSee('ch4113ng3');
});

it('rejects the hub verify handshake with the wrong token', function () {
    $connection = ProviderConnection::factory()->whatsapp()->create([
        'driver'      => 'meta_cloud',
        'credentials' => ['access_token' => 'mtok', 'phone_number_id' => 'PN1', 'waba_id' => '1111111111', 'app_secret' => 'msecret'],
    ]);

    $this->get(webhookUrl($connection, 'verify').'?hub_verify_token=wrong&hub_challenge=ch4113ng3')
        ->assertForbidden();
});

it('authenticates regional drivers by the shared URL secret', function () {
    $connection = ProviderConnection::factory()->sms()->create([
        'driver' => 'msegat',
    ]);

    $this->get(webhookUrl($connection).'?s='.$connection->webhook_secret.'&status=delivered&id=MS123')
        ->assertOk();

    $this->get(webhookUrl($connection).'?s=wrong-secret')
        ->assertForbidden();
});

it('returns 404 for an unknown connection', function () {
    $this->get(route('automation.webhooks.receive', ['msegat', 'unknown-uuid']))
        ->assertNotFound();
});

it('processes a MessageBird delivery report sent as GET query parameters', function () {
    $connection = ProviderConnection::factory()->sms()->create(['driver' => 'messagebird']);

    $log = smsMessageLog($connection, 'MB789');

    $this->get(webhookUrl($connection).'?s='.$connection->webhook_secret.'&id=MB789&status=delivered')
        ->assertOk();

    expect($log->refresh()->status->value)->toBe('delivered');
});

it('revokes phone consent on an inbound STOP message', function () {
    $connection = ProviderConnection::factory()->whatsapp()->create([
        'driver'      => 'meta_cloud',
        'credentials' => ['access_token' => 'mtok', 'phone_number_id' => 'PN1', 'waba_id' => '1111111111', 'app_secret' => 'msecret'],
    ]);

    $contact = Contact::factory()->create([
        'channel_id' => core()->getDefaultChannel()->id,
        'phone_e164' => '+966501234567',
    ]);

    app(ConsentService::class)->grant($contact, MessageChannel::Whatsapp, ConsentSource::Checkout);

    $body = json_encode([
        'entry' => [
            ['changes' => [
                ['value' => ['messages' => [
                    ['from' => '966501234567', 'text' => ['body' => 'STOP']],
                ]]],
            ]],
        ],
    ]);

    $signature = 'sha256='.hash_hmac('sha256', $body, 'msecret');

    $this->call('POST', webhookUrl($connection), [], [], [], ['HTTP_X-Hub-Signature-256' => $signature, 'CONTENT_TYPE' => 'application/json'], $body)
        ->assertOk();

    expect(app(ConsentService::class)->status($contact->refresh(), MessageChannel::Whatsapp))->toBe(ConsentStatus::Revoked)
        ->and(Consent::query()->where('contact_id', $contact->id)->where('channel', MessageChannel::Whatsapp->value)->first()?->source)
        ->toBe(ConsentSource::WhatsappOptout);
});

it('revokes consent only for contacts in the sales channels the connection serves', function () {
    $connection = ProviderConnection::factory()->sms()->create([
        'driver' => 'msegat',
    ]);

    $defaultChannelId = core()->getDefaultChannel()->id;

    $otherChannelId = Channel::factory()->create()->id;

    app(ProviderConnectionRepository::class)->setDefault($defaultChannelId, MessageChannel::Sms, $connection->id);

    $servedContact = Contact::factory()->create(['channel_id' => $defaultChannelId, 'phone_e164' => '+966501234567']);

    $otherContact = Contact::factory()->create(['channel_id' => $otherChannelId, 'phone_e164' => '+966501234567']);

    foreach ([$servedContact, $otherContact] as $contact) {
        app(ConsentService::class)->grant($contact, MessageChannel::Sms, ConsentSource::Checkout);
    }

    app(WebhookProcessor::class)->processInbound(
        $connection,
        new InboundMessage(from: '+966501234567', body: ' stop '),
    );

    expect(app(ConsentService::class)->status($servedContact->refresh(), MessageChannel::Sms))->toBe(ConsentStatus::Revoked)
        ->and(app(ConsentService::class)->status($otherContact->refresh(), MessageChannel::Sms))->toBe(ConsentStatus::Granted);
});

it('parses status and inbound payloads for every driver', function (string $driver) {
    $connection = ProviderConnection::factory()->create([
        'message_channel' => str_contains($driver, 'whatsapp') || $driver === 'meta_cloud' ? 'whatsapp' : 'sms',
        'driver'          => $driver,
    ]);

    $provider = app(ProviderManager::class)->driver($connection);

    $request = match ($driver) {
        'twilio', 'twilio_whatsapp' => Request::create('/', 'POST', ['MessageSid' => 'SM1', 'MessageStatus' => 'delivered']),
        'meta_cloud'                => Request::create('/', 'POST', ['entry' => [['changes' => [['value' => ['statuses' => [['id' => 'wamid.1', 'status' => 'delivered']]]]]]]]),
        'vonage'                    => Request::create('/', 'POST', ['messageId' => 'V1', 'status' => 'delivered']),
        'messagebird'               => Request::create('/', 'GET', ['id' => 'MB1', 'status' => 'delivered']),
    };

    $updates = $provider->parseStatusWebhook($request, $connection);

    expect($updates)->not->toBeEmpty()
        ->and($updates[0]->status)->toBe('delivered');
})->with(['twilio', 'twilio_whatsapp', 'meta_cloud', 'vonage', 'messagebird']);

it('treats buffered and scheduled delivery reports as no-ops', function () {
    $connection = ProviderConnection::factory()->sms()->create(['driver' => 'messagebird']);

    $messagebird = app(MessageBirdSmsProvider::class);

    foreach (['buffered', 'scheduled'] as $status) {
        $request = Request::create('/', 'GET', ['id' => 'MB1', 'status' => $status]);

        expect($messagebird->parseStatusWebhook($request, $connection))->toBe([]);
    }

    $vonage = app(VonageSmsProvider::class);

    $request = Request::create('/', 'POST', ['messageId' => 'V1', 'status' => 'buffered']);

    expect($vonage->parseStatusWebhook($request, $connection))->toBe([]);
});

it('treats unknown Meta statuses as no-ops', function () {
    $connection = ProviderConnection::factory()->whatsapp()->create([
        'driver'      => 'meta_cloud',
        'credentials' => ['access_token' => 'mtok', 'phone_number_id' => 'PN1', 'waba_id' => '1111111111', 'app_secret' => 'msecret'],
    ]);

    $meta = app(MetaCloudWhatsAppProvider::class);

    $request = Request::create('/', 'POST', ['entry' => [
        ['changes' => [['value' => ['statuses' => [['id' => 'wamid.9', 'status' => 'warning']]]]]],
    ]]);

    expect($meta->parseStatusWebhook($request, $connection))->toBe([]);
});

it('parses inbound messages for the opt-out capable drivers', function () {
    $connection = ProviderConnection::factory()->whatsapp()->create([
        'driver'      => 'meta_cloud',
        'credentials' => ['access_token' => 'mtok', 'phone_number_id' => 'PN1', 'waba_id' => '1111111111', 'app_secret' => 'msecret'],
    ]);

    $meta = app(MetaCloudWhatsAppProvider::class);

    $request = Request::create('/', 'POST', ['entry' => [
        ['changes' => [['value' => ['messages' => [['from' => '966501234567', 'text' => ['body' => 'hello']]]]]]],
    ]]);

    $message = $meta->parseInbound($request, $connection);

    expect($message?->from)->toBe('966501234567')
        ->and($message->body)->toBe('hello');

    $twilio = app(TwilioWhatsAppProvider::class);

    $request = Request::create('/', 'POST', ['From' => 'whatsapp:+966501234567', 'Body' => 'hi there']);

    $message = $twilio->parseInbound($request, $connection);

    expect($message?->from)->toBe('whatsapp:+966501234567')
        ->and($message->body)->toBe('hi there');
});

it('maps MessageBird delivery_failed reports onto failed with a truncated error', function () {
    $connection = ProviderConnection::factory()->sms()->create(['driver' => 'messagebird']);

    $log = smsMessageLog($connection, 'MB990');

    $reason = str_repeat('x', 400);

    $this->get(webhookUrl($connection).'?s='.$connection->webhook_secret.'&id=MB990&status=delivery_failed&statusReason='.$reason)
        ->assertOk();

    $log->refresh();

    expect($log->status)->toBe(MessageStatus::Failed)
        ->and(mb_strlen((string) $log->error_message))->toBeLessThanOrEqual(253);
});

it('does not disable stored templates when the provider sync fails', function () {
    $connection = ProviderConnection::factory()->whatsapp()->create([
        'driver'      => 'meta_cloud',
        'credentials' => ['access_token' => 'mtok', 'phone_number_id' => 'PN1', 'waba_id' => '1111111111', 'app_secret' => 'msecret'],
    ]);

    $template = WhatsAppTemplate::factory()->create([
        'provider_connection_id' => $connection->id,
        'name'                   => 'existing_template',
        'language'               => 'en_US',
        'status'                 => WhatsAppTemplateStatus::Approved->value,
    ]);

    Http::fake([
        'https://graph.facebook.com/*' => Http::response(['error' => ['message' => 'Bad token']], 401),
    ]);

    expect(fn () => (new SyncWhatsAppTemplates($connection))->handle(app(ProviderManager::class)))
        ->toThrow(RuntimeException::class);

    expect($template->refresh()->status)->toBe(WhatsAppTemplateStatus::Approved->value);
});
