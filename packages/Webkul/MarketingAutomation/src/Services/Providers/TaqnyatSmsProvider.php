<?php

namespace Webkul\MarketingAutomation\Services\Providers;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Webkul\MarketingAutomation\DTOs\OutboundMessage;
use Webkul\MarketingAutomation\DTOs\SendResult;
use Webkul\MarketingAutomation\Models\ProviderConnection;

class TaqnyatSmsProvider extends AbstractHttpProvider
{
    /**
     * Describe the credential fields shown in the admin form.
     */
    public static function fields(): array
    {
        return [
            ['name' => 'bearer_token', 'label' => 'Bearer Token', 'type' => 'password', 'secret' => true, 'required' => true],
        ];
    }

    /**
     * Send an SMS through the Taqnyat API.
     */
    public function send(ProviderConnection $connection, OutboundMessage $message): SendResult
    {
        $payload = [
            'recipients' => [ltrim($message->to, '+')],
            'body'       => (string) $message->body,
            'sender'     => $connection->sender,
        ];

        try {
            $response = $this->http()
                ->withToken((string) ($connection->credentials['bearer_token'] ?? ''))
                ->post('https://api.taqnyat.sa/v1/messages', $payload);
        } catch (ConnectionException $exception) {
            return $this->classify($exception, fn () => ['', '']);
        }

        $statusCode = (int) ($response->json('statusCode') ?? $response->status());

        if ($statusCode === 201 && filled($response->json('messageId'))) {
            return SendResult::accepted((string) $response->json('messageId'));
        }

        return $this->classify(
            $response,
            fn (Response $r) => [(string) ($r->json('statusCode') ?? $r->status()), (string) $r->json('message', $r->body())],
        );
    }
}
