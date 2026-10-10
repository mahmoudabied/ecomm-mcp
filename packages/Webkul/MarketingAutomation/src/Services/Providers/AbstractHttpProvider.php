<?php

namespace Webkul\MarketingAutomation\Services\Providers;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;
use Webkul\MarketingAutomation\Contracts\Providers\MessagingProvider;
use Webkul\MarketingAutomation\DTOs\OutboundMessage;
use Webkul\MarketingAutomation\DTOs\SendResult;
use Webkul\MarketingAutomation\DTOs\StatusUpdate;
use Webkul\MarketingAutomation\Models\ProviderConnection;

abstract class AbstractHttpProvider implements MessagingProvider
{
    /**
     * Send the message through the vendor API.
     */
    abstract public function send(ProviderConnection $connection, OutboundMessage $message): SendResult;

    /**
     * Send a harmless probe message to verify the credentials.
     */
    public function testConnection(ProviderConnection $connection, string $to): SendResult
    {
        $message = new OutboundMessage(
            to: $to,
            channel: $connection->message_channel->value,
            body: trans('marketing_automation::app.providers.test-message'),
            template: null,
            messageUuid: (string) Str::uuid(),
        );

        return $this->send($connection, $message);
    }

    /**
     * Verify the shared-secret query parameter on the webhook URL.
     */
    public function verifyWebhook(Request $request, ProviderConnection $connection): bool
    {
        return hash_equals($connection->webhook_secret, (string) $request->query('s'));
    }

    /**
     * Parse a delivery status webhook. Drivers override this.
     *
     * @return array<int, StatusUpdate>
     */
    public function parseStatusWebhook(Request $request, ProviderConnection $connection): array
    {
        return [];
    }

    /**
     * Classify an HTTP outcome into a send result using the shared rules.
     *
     * @param  callable(Response): array{0: string|int, 1: string}  $extractError
     */
    protected function classify(Response|Throwable $response, callable $extractError): SendResult
    {
        if ($response instanceof Throwable) {
            return SendResult::retryable('connection_error', $response->getMessage());
        }

        [$code, $message] = $extractError($response);

        if ($response->status() === 429 || $response->status() >= 500) {
            return SendResult::retryable((string) $code, $message);
        }

        return SendResult::permanent((string) $code, $message);
    }

    /**
     * Get a hardened HTTP client for vendor calls.
     */
    protected function http(): PendingRequest
    {
        return Http::timeout(15)->connectTimeout(5)->acceptJson();
    }
}
