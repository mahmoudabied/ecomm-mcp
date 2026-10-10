<?php

namespace Webkul\MarketingAutomation\Services\Providers;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;
use Webkul\MarketingAutomation\Contracts\Providers\SupportsInboundOptOut;
use Webkul\MarketingAutomation\DTOs\InboundMessage;
use Webkul\MarketingAutomation\DTOs\OutboundMessage;
use Webkul\MarketingAutomation\DTOs\SendResult;
use Webkul\MarketingAutomation\DTOs\StatusUpdate;
use Webkul\MarketingAutomation\Models\ProviderConnection;

class VonageSmsProvider extends AbstractHttpProvider implements SupportsInboundOptOut
{
    /**
     * Describe the credential fields shown in the admin form.
     */
    public static function fields(): array
    {
        return [
            ['name' => 'api_key', 'label' => 'API Key', 'type' => 'text', 'secret' => false, 'required' => true],
            ['name' => 'api_secret', 'label' => 'API Secret', 'type' => 'password', 'secret' => true, 'required' => true],
        ];
    }

    /**
     * Send an SMS through the Vonage SMS API.
     */
    public function send(ProviderConnection $connection, OutboundMessage $message): SendResult
    {
        $credentials = $connection->credentials;

        $payload = [
            'api_key'    => $credentials['api_key'] ?? '',
            'api_secret' => $credentials['api_secret'] ?? '',
            'from'       => $connection->sender,
            'to'         => ltrim($message->to, '+'),
            'text'       => (string) $message->body,
            'type'       => 'unicode',
        ];

        if ($message->statusCallbackUrl) {
            $payload['callback'] = $message->statusCallbackUrl;
        }

        try {
            $response = $this->http()->post('https://rest.nexmo.com/sms/json', $payload);
        } catch (ConnectionException $exception) {
            return $this->classify($exception, fn () => ['', '']);
        }

        return $this->interpret($response, $message);
    }

    /**
     * Verify the shared-secret query parameter on the webhook URL.
     */
    public function verifyWebhook(Request $request, ProviderConnection $connection): bool
    {
        return parent::verifyWebhook($request, $connection);
    }

    /**
     * Parse a delivery status webhook.
     */
    public function parseStatusWebhook(Request $request, ProviderConnection $connection): array
    {
        $status = (string) $request->input('status');

        if (! in_array($status, ['delivered', 'failed', 'rejected', 'expired'], true)) {
            return [];
        }

        return [
            new StatusUpdate(
                providerMessageId: (string) $request->input('messageId'),
                status: $status === 'delivered' ? 'delivered' : 'failed',
                errorCode: null,
                errorMessage: $status !== 'delivered' ? $status : null,
                occurredAt: now()->toImmutable(),
            ),
        ];
    }

    /**
     * Parse an inbound SMS message.
     */
    public function parseInbound(Request $request, ProviderConnection $connection): ?InboundMessage
    {
        $body = (string) $request->input('text');

        if ($body === '') {
            return null;
        }

        return new InboundMessage(
            from: (string) $request->input('msisdn'),
            body: $body,
            type: 'text',
        );
    }

    /**
     * Interpret a Vonage response, which reports failures inside a 200 body.
     */
    protected function interpret(Response $response, OutboundMessage $message): SendResult
    {
        if ($response->status() === 401 || $response->status() === 403) {
            return SendResult::permanent(
                (string) ($response->json('error-code') ?? $response->status()),
                (string) $response->json('error-code-label', $response->body()),
            );
        }

        if ($this->isRetryableHttp($response)) {
            return SendResult::retryable((string) $response->status(), $response->body());
        }

        $messageReport = $response->json('messages.0') ?? [];

        if ((string) ($messageReport['status'] ?? '1') === '0') {
            return SendResult::accepted((string) $messageReport['message-id']);
        }

        if ((string) ($messageReport['status'] ?? '') === '1') {
            return SendResult::retryable(
                (string) $messageReport['status'],
                (string) ($messageReport['error-text'] ?? 'Throttled'),
            );
        }

        return SendResult::permanent(
            (string) ($messageReport['status'] ?? $response->status()),
            (string) ($messageReport['error-text'] ?? $response->body()),
        );
    }

    /**
     * Whether the HTTP status itself signals a retryable condition.
     */
    protected function isRetryableHttp(Response $response): bool
    {
        return $response->status() === 429 || $response->status() >= 500;
    }
}
