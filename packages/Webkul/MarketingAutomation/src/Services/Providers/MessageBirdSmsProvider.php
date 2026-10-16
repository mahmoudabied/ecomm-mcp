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
use Webkul\MarketingAutomation\Enums\MessageStatus;
use Webkul\MarketingAutomation\Models\ProviderConnection;

class MessageBirdSmsProvider extends AbstractHttpProvider implements SupportsInboundOptOut
{
    /**
     * Describe the credential fields shown in the admin form.
     */
    public static function fields(): array
    {
        return [
            ['name' => 'access_key', 'label' => 'Access Key', 'type' => 'password', 'secret' => true, 'required' => true],
        ];
    }

    /**
     * Send an SMS through the MessageBird Messages API.
     */
    public function send(ProviderConnection $connection, OutboundMessage $message): SendResult
    {
        $payload = [
            'originator' => $connection->sender,
            'recipients' => [(int) ltrim($message->to, '+')],
            'body'       => (string) $message->body,
            'datacoding' => 'auto',
        ];

        if ($message->statusCallbackUrl) {
            $payload['reportUrl'] = $message->statusCallbackUrl;
        }

        try {
            $response = $this->http()
                ->withHeaders(['Authorization' => 'AccessKey '.($connection->credentials['access_key'] ?? '')])
                ->post('https://rest.messagebird.com/messages', $payload);
        } catch (ConnectionException $exception) {
            return $this->classify($exception, fn () => ['', '']);
        }

        if ($response->successful() && filled($response->json('id'))) {
            return SendResult::accepted((string) $response->json('id'));
        }

        return $this->classify(
            $response,
            fn (Response $r) => [(string) $r->json('errors.0.code', $r->status()), (string) $r->json('errors.0.description', $r->body())],
        );
    }

    /**
     * Parse a delivery report delivered as query parameters; scheduled and
     * buffered reports are intermediate states and produce no update.
     */
    public function parseStatusWebhook(Request $request, ProviderConnection $connection): array
    {
        $status = (string) $request->query('status');

        $mapped = match ($status) {
            'sent'                                 => MessageStatus::Sent,
            'delivered'                            => MessageStatus::Delivered,
            'delivery_failed', 'failed', 'expired' => MessageStatus::Failed,
            default                                => null,
        };

        if ($mapped === null) {
            return [];
        }

        $failed = $mapped === MessageStatus::Failed;

        return [
            new StatusUpdate(
                providerMessageId: (string) $request->query('id'),
                status: $mapped->value,
                errorCode: $failed && filled($request->query('statusErrorCode')) ? (string) $request->query('statusErrorCode') : null,
                errorMessage: $failed ? (string) ($request->query('statusReason') ?: $status) : null,
                occurredAt: now()->toImmutable(),
            ),
        ];
    }

    /**
     * Parse an inbound SMS message.
     */
    public function parseInbound(Request $request, ProviderConnection $connection): ?InboundMessage
    {
        $body = (string) $request->input('body');

        if ($body === '') {
            return null;
        }

        return new InboundMessage(
            from: (string) $request->input('originator'),
            body: $body,
            type: 'text',
        );
    }
}
