<?php

namespace Webkul\MarketingAutomation\Services\Providers;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Webkul\MarketingAutomation\DTOs\OutboundMessage;
use Webkul\MarketingAutomation\DTOs\SendResult;
use Webkul\MarketingAutomation\Models\ProviderConnection;

class MsegatSmsProvider extends AbstractHttpProvider
{
    /**
     * Describe the credential fields shown in the admin form.
     */
    public static function fields(): array
    {
        return [
            ['name' => 'username', 'label' => 'Username', 'type' => 'text', 'secret' => false, 'required' => true],
            ['name' => 'api_key', 'label' => 'API Key', 'type' => 'password', 'secret' => true, 'required' => true],
        ];
    }

    /**
     * Send an SMS through the Msegat gateway.
     */
    public function send(ProviderConnection $connection, OutboundMessage $message): SendResult
    {
        $payload = [
            'userName'    => $connection->credentials['username'] ?? '',
            'apiKey'      => $connection->credentials['api_key'] ?? '',
            'numbers'     => ltrim($message->to, '+'),
            'userSender'  => $connection->sender,
            'msg'         => (string) $message->body,
            'msgEncoding' => 'UTF8',
        ];

        try {
            $response = $this->http()->post('https://www.msegat.com/gw/sendsms.php', $payload);
        } catch (ConnectionException $exception) {
            return $this->classify($exception, fn () => ['', '']);
        }

        return $this->interpret($response, $message);
    }

    /**
     * Interpret a Msegat response, which reports its own status codes.
     */
    protected function interpret(Response $response, OutboundMessage $message): SendResult
    {
        $code = (string) $response->json('code', $response->status());

        if (in_array($code, ['1', 'M0000'], true)) {
            return SendResult::accepted(
                (string) ($response->json('messageId') ?? $message->messageUuid),
            );
        }

        return $this->classify(
            $response,
            fn (Response $r) => [(string) $r->json('code', $r->status()), (string) $r->json('message', $r->body())],
        );
    }
}
