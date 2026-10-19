<?php

use Illuminate\Http\Client\ConnectionException;
use Amacraft\MarketingAutomation\DTOs\OutboundMessage;
use Amacraft\MarketingAutomation\DTOs\WhatsAppTemplateCall;
use Amacraft\MarketingAutomation\Enums\SendStatus;
use Amacraft\MarketingAutomation\Models\ProviderConnection;
use Amacraft\MarketingAutomation\Services\ProviderManager;
use Amacraft\MarketingAutomation\Services\Providers\MetaCloudWhatsAppProvider;
use Amacraft\MarketingAutomation\Tests\ProviderFixtures;

/**
 * Credential sets and request expectations for every launch driver.
 */
function driverCases(): array
{
    return [
        'twilio' => [
            'channel'       => 'sms',
            'credentials'   => ['account_sid' => 'ACtest1234567890', 'auth_token' => 'twilio-token', 'messaging_service_sid' => 'MGservice1'],
            'sender'        => '+15005550006',
            'assertRequest' => fn ($request) => str_starts_with($request->url(), 'https://api.twilio.com/2010-04-01/Accounts/ACtest1234567890/Messages.json')
                && str_starts_with((string) collect($request->header('Authorization'))->first(), 'Basic ')
                && $request['To'] === '+966501234567'
                && $request['Body'] !== null
                && $request['MessagingServiceSid'] === 'MGservice1',
        ],
        'vonage' => [
            'channel'       => 'sms',
            'credentials'   => ['api_key' => 'vk1', 'api_secret' => 'vs2'],
            'sender'        => 'Bagisto',
            'assertRequest' => fn ($request) => $request->url() === 'https://rest.nexmo.com/sms/json'
                && $request['api_key'] === 'vk1'
                && $request['api_secret'] === 'vs2'
                && $request['to'] === '966501234567'
                && $request['type'] === 'unicode',
        ],
        'messagebird' => [
            'channel'       => 'sms',
            'credentials'   => ['access_key' => 'mbkey'],
            'sender'        => 'Bagisto',
            'assertRequest' => fn ($request) => $request->url() === 'https://rest.messagebird.com/messages'
                && collect($request->header('Authorization'))->first() === 'AccessKey mbkey'
                && $request['recipients'] === [966501234567]
                && $request['datacoding'] === 'auto',
        ],
        'unifonic' => [
            'channel'       => 'sms',
            'credentials'   => ['app_sid' => 'usid'],
            'sender'        => 'Bagisto',
            'assertRequest' => fn ($request) => $request->url() === 'https://el.cloud.unifonic.com/rest/SMS/messages'
                && $request['AppSid'] === 'usid'
                && $request['Recipient'] === '966501234567',
        ],
        'msegat' => [
            'channel'       => 'sms',
            'credentials'   => ['username' => 'muser', 'api_key' => 'mkey'],
            'sender'        => 'Bagisto',
            'assertRequest' => fn ($request) => $request->url() === 'https://www.msegat.com/gw/sendsms.php'
                && $request['userName'] === 'muser'
                && $request['apiKey'] === 'mkey'
                && $request['numbers'] === '966501234567'
                && $request['msgEncoding'] === 'UTF8',
        ],
        'taqnyat' => [
            'channel'       => 'sms',
            'credentials'   => ['bearer_token' => 'tqtoken'],
            'sender'        => 'Bagisto',
            'assertRequest' => fn ($request) => $request->url() === 'https://api.taqnyat.sa/v1/messages'
                && collect($request->header('Authorization'))->first() === 'Bearer tqtoken'
                && $request['recipients'] === ['966501234567']
                && $request['sender'] === 'Bagisto',
        ],
        'meta_cloud' => [
            'channel'       => 'whatsapp',
            'credentials'   => ['access_token' => 'mtok', 'phone_number_id' => 'PN1', 'waba_id' => '1111111111', 'app_secret' => 'msecret'],
            'sender'        => 'Bagisto',
            'template'      => new WhatsAppTemplateCall(name: 'hello_world', language: 'en_US', bodyParams: ['Sara']),
            'assertRequest' => fn ($request) => $request->url() === 'https://graph.facebook.com/v21.0/PN1/messages'
                && collect($request->header('Authorization'))->first() === 'Bearer mtok'
                && $request['messaging_product'] === 'whatsapp'
                && $request['to'] === '966501234567'
                && $request['type'] === 'template'
                && $request['template']['name'] === 'hello_world'
                && $request['template']['language']['code'] === 'en_US'
                && $request['template']['components'][0]['parameters'][0]['text'] === 'Sara',
        ],
        'twilio_whatsapp' => [
            'channel'       => 'whatsapp',
            'credentials'   => ['account_sid' => 'ACtest1234567890', 'auth_token' => 'twilio-token'],
            'sender'        => 'whatsapp:+14155238886',
            'template'      => new WhatsAppTemplateCall(name: 'HXaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', language: 'en', bodyParams: [1 => 'Sara']),
            'assertRequest' => fn ($request) => str_starts_with($request->url(), 'https://api.twilio.com/2010-04-01/Accounts/ACtest1234567890/Messages.json')
                && $request['To'] === 'whatsapp:+966501234567'
                && $request['From'] === 'whatsapp:+14155238886'
                && $request['ContentSid'] === 'HXaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa'
                && str_contains((string) $request['ContentVariables'], 'Sara'),
        ],
    ];
}

function makeConnection(string $driver): ProviderConnection
{
    $case = driverCases()[$driver];

    return ProviderConnection::factory()->create([
        'message_channel' => $case['channel'],
        'driver'          => $driver,
        'credentials'     => $case['credentials'],
        'sender'          => $case['sender'],
    ]);
}

function makeOutbound(ProviderConnection $connection): OutboundMessage
{
    $case = driverCases()[$connection->driver];

    return new OutboundMessage(
        to: '+966501234567',
        channel: $case['channel'],
        body: 'Hello from Bagisto',
        template: $case['template'] ?? null,
        messageUuid: 'uuid-'.$connection->driver,
    );
}

it('sends successfully and targets the right endpoint for every driver', function (string $driver) {
    $connection = makeConnection($driver);
    $provider = app(ProviderManager::class)->driver($connection);

    Http::fake([
        '*' => Http::response(
            ProviderFixtures::body($driver, 'send_success'),
            ProviderFixtures::status($driver, 'send_success'),
        ),
    ]);

    $result = $provider->send($connection, makeOutbound($connection));

    expect($result->status)->toBe(SendStatus::Accepted)
        ->and($result->providerMessageId)->not->toBeEmpty();

    Http::assertSent(driverCases()[$driver]['assertRequest']);
})->with(['twilio', 'vonage', 'messagebird', 'unifonic', 'msegat', 'taqnyat', 'meta_cloud', 'twilio_whatsapp']);

it('classifies an authentication error as permanent for every driver', function (string $driver) {
    $connection = makeConnection($driver);
    $provider = app(ProviderManager::class)->driver($connection);

    Http::fake([
        '*' => Http::response(
            ProviderFixtures::body($driver, 'send_auth_error'),
            ProviderFixtures::status($driver, 'send_auth_error'),
        ),
    ]);

    $result = $provider->send($connection, makeOutbound($connection));

    expect($result->status)->toBe(SendStatus::PermanentError)
        ->and($result->errorMessage)->not->toBeEmpty();
})->with(['twilio', 'vonage', 'messagebird', 'unifonic', 'msegat', 'taqnyat', 'meta_cloud', 'twilio_whatsapp']);

it('classifies rate limiting as retryable for every driver', function (string $driver) {
    $connection = makeConnection($driver);
    $provider = app(ProviderManager::class)->driver($connection);

    Http::fake([
        '*' => Http::response(
            ProviderFixtures::body($driver, 'send_rate_limited'),
            ProviderFixtures::status($driver, 'send_rate_limited'),
        ),
    ]);

    $result = $provider->send($connection, makeOutbound($connection));

    expect($result->status)->toBe(SendStatus::RetryableError);
})->with(['twilio', 'vonage', 'messagebird', 'unifonic', 'msegat', 'taqnyat', 'meta_cloud', 'twilio_whatsapp']);

it('classifies a dropped connection as retryable for every driver', function (string $driver) {
    $connection = makeConnection($driver);
    $provider = app(ProviderManager::class)->driver($connection);

    Http::fake(fn () => throw new ConnectionException('cURL error 28: Connection timed out'));

    $result = $provider->send($connection, makeOutbound($connection));

    expect($result->status)->toBe(SendStatus::RetryableError)
        ->and($result->errorMessage)->toContain('timed out');
})->with(['twilio', 'vonage', 'messagebird', 'unifonic', 'msegat', 'taqnyat', 'meta_cloud', 'twilio_whatsapp']);

it('classifies a Meta re-engagement rejection as permanent', function () {
    $connection = makeConnection('meta_cloud');
    $provider = app(ProviderManager::class)->driver($connection);

    Http::fake([
        '*' => Http::response(
            ProviderFixtures::body('meta_cloud', 'send_outside_window'),
            ProviderFixtures::status('meta_cloud', 'send_outside_window'),
        ),
    ]);

    $result = $provider->send($connection, makeOutbound($connection));

    expect($result->status)->toBe(SendStatus::PermanentError)
        ->and($result->errorCode)->toBe('131047');
});

it('tests a Meta connection with the hello_world template', function () {
    $connection = makeConnection('meta_cloud');
    $provider = app(ProviderManager::class)->driver($connection);

    Http::fake([
        '*' => Http::response(
            ProviderFixtures::body('meta_cloud', 'send_success'),
            ProviderFixtures::status('meta_cloud', 'send_success'),
        ),
    ]);

    $result = $provider->testConnection($connection, '+966501234567');

    expect($result->status)->toBe(SendStatus::Accepted);

    Http::assertSent(function ($request) {
        return $request['type'] === 'template'
            && $request['template']['name'] === 'hello_world'
            && $request['template']['language']['code'] === 'en_US';
    });
});

it('refuses to test a Twilio WhatsApp connection without synced templates', function () {
    $connection = makeConnection('twilio_whatsapp');
    $provider = app(ProviderManager::class)->driver($connection);

    $result = $provider->testConnection($connection, '+966501234567');

    expect($result->status)->toBe(SendStatus::PermanentError)
        ->and($result->errorCode)->toBe('no_template');
});

it('follows pagination when fetching Meta templates', function () {
    $connection = makeConnection('meta_cloud');
    $provider = app(MetaCloudWhatsAppProvider::class);

    Http::fake([
        'https://graph.facebook.com/v21.0/1111111111/message_templates?limit=200' => Http::response(
            ProviderFixtures::body('meta_cloud', 'templates'),
            200,
        ),
        'https://graph.facebook.com/v21.0/1111111111/message_templates?limit=200&after=MQZDZD' => Http::response(
            ProviderFixtures::body('meta_cloud', 'templates_page2'),
            200,
        ),
    ]);

    $templates = $provider->fetchTemplates($connection);

    expect($templates)->toHaveCount(4)
        ->and($templates[0]->name)->toBe('hello_world')
        ->and($templates[0]->status)->toBe('approved')
        ->and($templates[0]->variableCount)->toBe(1)
        ->and(collect($templates)->pluck('name'))->toContain('cart_nudge');
});

it('maps Twilio Content approvals onto templates and follows pagination', function () {
    $connection = makeConnection('twilio_whatsapp');
    $provider = app(ProviderManager::class)->driver($connection);

    Http::fake([
        'https://content.twilio.com/v1/ContentAndApprovals?PageSize=100' => Http::response(
            ProviderFixtures::body('twilio_whatsapp', 'templates'),
            200,
        ),
        'https://content.twilio.com/v1/ContentAndApprovals?PageSize=100&Page=1' => Http::response(
            ProviderFixtures::body('twilio_whatsapp', 'templates_page2'),
            200,
        ),
    ]);

    $templates = $provider->fetchTemplates($connection);

    expect($templates)->toHaveCount(3)
        ->and($templates[0]->externalId)->toBe('HXaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa')
        ->and($templates[0]->status)->toBe('approved')
        ->and($templates[1]->status)->toBe('pending')
        ->and($templates[2]->name)->toBe('HXcccccccccccccccccccccccccccccccc');

    Http::assertSent(fn ($request) => str_starts_with(
        (string) collect($request->header('Authorization'))->first(),
        'Basic ',
    ));
});

it('sends Twilio ContentVariables as an object keyed from "1"', function () {
    $connection = makeConnection('twilio_whatsapp');
    $provider = app(ProviderManager::class)->driver($connection);

    Http::fake([
        '*' => Http::response(
            ProviderFixtures::body('twilio_whatsapp', 'send_success'),
            ProviderFixtures::status('twilio_whatsapp', 'send_success'),
        ),
    ]);

    $result = $provider->send($connection, makeOutbound($connection));

    expect($result->status)->toBe(SendStatus::Accepted);

    Http::assertSent(function ($request) {
        $variables = json_decode((string) $request['ContentVariables'], true);

        return $variables === ['1' => 'Sara'];
    });
});

it('omits Twilio ContentVariables when the template has no parameters', function () {
    $connection = makeConnection('twilio_whatsapp');
    $provider = app(ProviderManager::class)->driver($connection);

    Http::fake([
        '*' => Http::response(
            ProviderFixtures::body('twilio_whatsapp', 'send_success'),
            ProviderFixtures::status('twilio_whatsapp', 'send_success'),
        ),
    ]);

    $provider->send($connection, new OutboundMessage(
        to: '+966501234567',
        channel: 'whatsapp',
        body: null,
        template: new WhatsAppTemplateCall(name: 'HXaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', language: 'en'),
        messageUuid: 'uuid-no-params',
    ));

    Http::assertSent(fn ($request) => $request['ContentSid'] === 'HXaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa'
        && ! array_key_exists('ContentVariables', $request->data()));
});

it('fails loudly when the Twilio template fetch is rejected', function () {
    $connection = makeConnection('twilio_whatsapp');
    $provider = app(ProviderManager::class)->driver($connection);

    Http::fake([
        'https://content.twilio.com/*' => Http::response(['message' => 'Authenticate'], 401),
    ]);

    $provider->fetchTemplates($connection);
})->throws(RuntimeException::class);
