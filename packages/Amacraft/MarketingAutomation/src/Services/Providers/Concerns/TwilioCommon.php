<?php

namespace Amacraft\MarketingAutomation\Services\Providers\Concerns;

use Amacraft\MarketingAutomation\DTOs\InboundMessage;
use Amacraft\MarketingAutomation\DTOs\SendResult;
use Amacraft\MarketingAutomation\DTOs\StatusUpdate;
use Amacraft\MarketingAutomation\Models\ProviderConnection;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;

trait TwilioCommon
{
    /**
     * Credential fields shared by the Twilio SMS and WhatsApp drivers.
     */
    public static function twilioFields(): array
    {
        return [
            ['name' => 'account_sid', 'label' => 'Account SID', 'type' => 'text', 'secret' => false, 'required' => true],
            ['name' => 'auth_token', 'label' => 'Auth Token', 'type' => 'password', 'secret' => true, 'required' => true],
        ];
    }

    /**
     * Send a request against the Twilio Messages endpoint and classify the outcome.
     *
     * @param  array<string, string>  $params
     */
    protected function postMessage(ProviderConnection $connection, array $params): SendResult
    {
        $sid = $connection->credentials['account_sid'] ?? '';
        $token = $connection->credentials['auth_token'] ?? '';

        try {
            $response = $this->http()
                ->withBasicAuth($sid, $token)
                ->asForm()
                ->post($this->messagesEndpoint($sid), $params);
        } catch (ConnectionException $exception) {
            return $this->classify($exception, fn () => ['', '']);
        }

        if ($response->successful() && filled($response->json('sid'))) {
            return SendResult::accepted((string) $response->json('sid'));
        }

        return $this->classify(
            $response,
            fn (Response $r) => [(string) $r->json('code'), (string) $r->json('message', $r->body())],
        );
    }

    /**
     * Validate the X-Twilio-Signature header on an inbound webhook request.
     * The URL that is signed is the public one Twilio called; behind a proxy
     * that does not pass X-Forwarded-Proto the request URL differs, so the
     * public base can be pinned via marketing-automation.webhooks.public_base_url.
     */
    protected function verifyTwilioSignature(Request $request, ProviderConnection $connection): bool
    {
        $signature = (string) $request->header('X-Twilio-Signature');

        if ($signature === '') {
            return false;
        }

        $url = $this->signedUrl($request);

        $params = $request->post() ?: [];

        ksort($params);

        $data = $url;

        foreach ($params as $key => $value) {
            $data .= $key.$value;
        }

        $expected = base64_encode(hash_hmac('sha1', $data, (string) ($connection->credentials['auth_token'] ?? ''), true));

        return hash_equals($expected, $signature);
    }

    /**
     * The URL Twilio signed: the configured public base when set, otherwise
     * the request's own full URL.
     */
    protected function signedUrl(Request $request): string
    {
        $base = (string) config('marketing-automation.webhooks.public_base_url');

        if (blank($base)) {
            return $request->fullUrl();
        }

        return rtrim($base, '/').$request->getRequestUri();
    }

    /**
     * Map the Twilio status webhook payload onto status updates.
     *
     * @return array<int, StatusUpdate>
     */
    protected function parseTwilioStatus(Request $request): array
    {
        $status = (string) $request->input('MessageStatus');

        $mapped = match ($status) {
            'queued', 'sent'        => 'sent',
            'delivered'             => 'delivered',
            'read'                  => 'read',
            'failed', 'undelivered' => 'failed',
            default                 => null,
        };

        if ($mapped === null) {
            return [];
        }

        return [
            new StatusUpdate(
                providerMessageId: (string) $request->input('MessageSid'),
                status: $mapped,
                errorCode: $request->filled('ErrorCode') ? (string) $request->input('ErrorCode') : null,
                errorMessage: null,
                occurredAt: now()->toImmutable(),
            ),
        ];
    }

    /**
     * Read an inbound Twilio message, when the request carries a body.
     */
    protected function parseTwilioInbound(Request $request): ?InboundMessage
    {
        $body = (string) $request->input('Body');

        if ($body === '') {
            return null;
        }

        return new InboundMessage(
            from: (string) $request->input('From'),
            body: $body,
            type: 'text',
        );
    }

    /**
     * Build the Messages endpoint URL for an account.
     */
    protected function messagesEndpoint(string $accountSid): string
    {
        return "https://api.twilio.com/2010-04-01/Accounts/{$accountSid}/Messages.json";
    }
}
