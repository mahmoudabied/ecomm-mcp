<?php

use Illuminate\Support\Facades\Mail;
use Webkul\MarketingAutomation\Mail\AutomationMail;
use Webkul\MarketingAutomation\Models\Contact;
use Webkul\MarketingAutomation\Models\MessageEvent;
use Webkul\MarketingAutomation\Models\MessageLog;
use Webkul\MarketingAutomation\Models\ProviderConnection;
use Webkul\MarketingAutomation\Rendering\LinkTracker;

beforeEach(function () {
    Mail::fake();
});

/**
 * Send a marketing email for a contact and return its log.
 */
function trackedSend(): MessageLog
{
    $contact = Contact::factory()->create([
        'channel_id' => core()->getAllChannels()->first()->id,
        'email'      => 'tracked@example.com',
    ]);

    $log = MessageLog::factory()->create([
        'contact_id'      => $contact->id,
        'channel_id'      => $contact->channel_id,
        'message_channel' => 'email',
        'is_marketing'    => true,
        'recipient'       => $contact->email,
        'status'          => 'queued',
    ]);

    $html = '<html><body>'
        .'<a href="https://shop.test/promo">Promo</a>'
        .'<a href="https://shop.test/sale">Sale</a>'
        .'</body></html>';

    $tracked = app(LinkTracker::class)->rewrite($log, $html);

    Mail::to($log->recipient)->send(new AutomationMail(
        subjectText: 'Tracked',
        bodyHtml: $tracked,
        fromAddress: 'shop@test.local',
        fromName: 'Shop',
    ));

    return $log;
}

it('rewrites email links to tracking urls and appends the open pixel', function () {
    $log = trackedSend();

    $tracked = trackedHtml();

    expect(substr_count($tracked, 'automation/t/c/'))->toBe(2)
        ->and($tracked)->toContain('automation/t/o/')
        ->and($tracked)->toContain('<img src="')
        ->and($log->refresh()->links)->toHaveCount(2);
});

/**
 * The HTML the recipient would see, pulled straight from the sent mailable.
 */
function trackedHtml(): string
{
    $mail = null;

    Mail::assertSent(AutomationMail::class, function (AutomationMail $sent) use (&$mail) {
        $mail = $sent;

        return true;
    });

    $property = new ReflectionProperty(AutomationMail::class, 'bodyHtml');

    return (string) $property->getValue($mail);
}

it('records a click and redirects to the stored url', function () {
    $log = trackedSend();

    $links = $log->refresh()->links;

    $hash = array_key_first($links);

    $this->get(route('automation.track.click', [$log->uuid.'.'.trackingSig($log->uuid), $hash]))
        ->assertRedirect($links[$hash]);

    expect($log->refresh()->clicked_at)->not->toBeNull()
        ->and(MessageEvent::query()->where('message_log_id', $log->id)->where('type', 'clicked')->exists())->toBeTrue();
});

it('refuses an unknown link hash instead of open redirecting', function () {
    $log = trackedSend();

    $this->get(route('automation.track.click', [$log->uuid.'.'.trackingSig($log->uuid), 'deadbeef0000']))
        ->assertNotFound();

    expect($log->refresh()->clicked_at)->toBeNull();
});

it('refuses a tracking token with a bad signature', function () {
    $log = trackedSend();

    $hash = array_key_first($log->refresh()->links);

    $this->get(route('automation.track.click', [$log->uuid.'.tamperedsig1234', $hash]))
        ->assertNotFound();
});

it('records only the first open timestamp but an event per open', function () {
    $log = trackedSend();

    $pixelUrl = route('automation.track.open', $log->uuid.'.'.trackingSig($log->uuid));

    $first = $this->get($pixelUrl);

    expect($first->status())->toBe(200)
        ->and($first->headers->get('content-type'))->toContain('image/gif')
        ->and($first->headers->get('cache-control'))->toContain('no-store');

    $openedAt = $log->refresh()->opened_at;

    $this->travelTo(now()->addMinutes(5));

    $this->get($pixelUrl)->assertOk();

    expect($log->refresh()->opened_at)->toEqual($openedAt)
        ->and(MessageEvent::query()->where('message_log_id', $log->id)->where('type', 'opened')->count())->toBe(2);
});

it('applies a twilio delivered webhook to the log', function () {
    $this->loginAsAdmin();

    $connection = ProviderConnection::factory()->sms()->create([
        'driver'      => 'twilio',
        'credentials' => ['account_sid' => 'AC123', 'auth_token' => 'tok'],
    ]);

    $log = MessageLog::factory()->create([
        'message_channel'        => 'sms',
        'provider_connection_id' => $connection->id,
        'provider_message_id'    => 'SMtrk1',
        'status'                 => 'sent',
        'sent_at'                => now(),
    ]);

    postSignedTwilio($connection, 'tok', ['MessageSid' => 'SMtrk1', 'MessageStatus' => 'delivered'])
        ->assertOk();

    expect($log->refresh()->status->value)->toBe('delivered')
        ->and($log->delivered_at)->not->toBeNull();
});

it('stores the provider error of a failed webhook', function () {
    $this->loginAsAdmin();

    $connection = ProviderConnection::factory()->sms()->create([
        'driver'      => 'twilio',
        'credentials' => ['account_sid' => 'AC123', 'auth_token' => 'tok'],
    ]);

    $log = MessageLog::factory()->create([
        'message_channel'        => 'sms',
        'provider_connection_id' => $connection->id,
        'provider_message_id'    => 'SMtrk2',
        'status'                 => 'sent',
    ]);

    postSignedTwilio($connection, 'tok', [
        'MessageSid'    => 'SMtrk2',
        'MessageStatus' => 'failed',
        'ErrorCode'     => '30003',
    ])->assertOk();

    expect($log->refresh()->status->value)->toBe('failed')
        ->and($log->error_code)->toBe('30003');
});

it('applies a meta read webhook to the log', function () {
    $this->loginAsAdmin();

    $connection = ProviderConnection::factory()->whatsapp()->create([
        'driver'      => 'meta_cloud',
        'credentials' => ['access_token' => 'mtok', 'phone_number_id' => 'PN1', 'waba_id' => '111', 'app_secret' => 'msecret'],
    ]);

    $log = MessageLog::factory()->create([
        'message_channel'        => 'whatsapp',
        'provider_connection_id' => $connection->id,
        'provider_message_id'    => 'wamid.trk3',
        'status'                 => 'delivered',
    ]);

    $url = route('automation.webhooks.receive', [$connection->driver, $connection->uuid]);

    $body = json_encode(['entry' => [['changes' => [['value' => ['statuses' => [['id' => 'wamid.trk3', 'status' => 'read']]]]]]]]);

    $this->call('POST', $url, [], [], [], [
        'HTTP_X-Hub-Signature-256' => 'sha256='.hash_hmac('sha256', $body, 'msecret'),
        'CONTENT_TYPE'             => 'application/json',
    ], $body)->assertOk();

    expect($log->refresh()->status->value)->toBe('read');
});

/**
 * Post a Twilio webhook payload with its expected signature.
 */
function postSignedTwilio(ProviderConnection $connection, string $authToken, array $params)
{
    $url = route('automation.webhooks.receive', [$connection->driver, $connection->uuid]);

    return test()->post($url, $params, ['X-Twilio-Signature' => trackTwilioSignature($url, $params, $authToken)]);
}

function trackTwilioSignature(string $url, array $params, string $authToken): string
{
    ksort($params);

    $data = $url;

    foreach ($params as $key => $value) {
        $data .= $key.$value;
    }

    return base64_encode(hash_hmac('sha1', $data, $authToken, true));
}

/**
 * The tracking signature the LinkTracker produces for a message uuid.
 */
function trackingSig(string $uuid): string
{
    return app(LinkTracker::class)->signature($uuid);
}
