<?php

namespace Webkul\MarketingAutomation\Services\Providers;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Webkul\MarketingAutomation\DTOs\OutboundMessage;
use Webkul\MarketingAutomation\DTOs\SendResult;
use Webkul\MarketingAutomation\Models\ProviderConnection;

class UnifonicSmsProvider extends AbstractHttpProvider
{
    /**
     * Describe the credential fields shown in the admin form.
     */
    public static function fields(): array
    {
        return [
            ['name' => 'app_sid', 'label' => 'App SID', 'type' => 'password', 'secret' => true, 'required' => true],
        ];
    }

    /**
     * Send an SMS through the Unifonic REST API.
     */
    public function send(ProviderConnection $connection, OutboundMessage $message): SendResult
    {
        $payload = [
            'AppSid'        => $connection->credentials['app_sid'] ?? '',
            'SenderID'      => $connection->sender,
            'Recipient'     => ltrim($message->to, '+'),
            'Body'          => (string) $message->body,
            'CorrelationID' => $message->messageUuid,
        ];

        try {
            $response = $this->http()->asForm()->post('https://el.cloud.unifonic.com/rest/SMS/messages', $payload);
        } catch (ConnectionException $exception) {
            return $this->classify($exception, fn () => ['', '']);
        }

        if ($response->json('success') === true && filled($response->json('data.MessageID'))) {
            return SendResult::accepted((string) $response->json('data.MessageID'));
        }

        return $this->classify(
            $response,
            fn (Response $r) => [(string) $r->json('errorCode', $r->status()), (string) $r->json('message', $r->body())],
        );
    }
}
