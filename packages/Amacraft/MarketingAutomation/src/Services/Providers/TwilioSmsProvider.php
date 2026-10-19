<?php

namespace Amacraft\MarketingAutomation\Services\Providers;

use Illuminate\Http\Request;
use Amacraft\MarketingAutomation\Contracts\Providers\SupportsInboundOptOut;
use Amacraft\MarketingAutomation\DTOs\InboundMessage;
use Amacraft\MarketingAutomation\DTOs\OutboundMessage;
use Amacraft\MarketingAutomation\DTOs\SendResult;
use Amacraft\MarketingAutomation\Models\ProviderConnection;
use Amacraft\MarketingAutomation\Services\Providers\Concerns\TwilioCommon;

class TwilioSmsProvider extends AbstractHttpProvider implements SupportsInboundOptOut
{
    use TwilioCommon;

    /**
     * Describe the credential fields shown in the admin form.
     */
    public static function fields(): array
    {
        return [
            ...self::twilioFields(),
            ['name' => 'messaging_service_sid', 'label' => 'Messaging Service SID', 'type' => 'text', 'secret' => false, 'required' => false],
        ];
    }

    /**
     * Send an SMS through the Twilio Messages API.
     */
    public function send(ProviderConnection $connection, OutboundMessage $message): SendResult
    {
        $params = [
            'To'   => $message->to,
            'Body' => (string) $message->body,
        ];

        if (filled($connection->credentials['messaging_service_sid'] ?? null)) {
            $params['MessagingServiceSid'] = $connection->credentials['messaging_service_sid'];
        } else {
            $params['From'] = $connection->sender;
        }

        if ($message->statusCallbackUrl) {
            $params['StatusCallback'] = $message->statusCallbackUrl;
        }

        return $this->postMessage($connection, $params);
    }

    /**
     * Verify the Twilio signature on an inbound webhook.
     */
    public function verifyWebhook(Request $request, ProviderConnection $connection): bool
    {
        return $this->verifyTwilioSignature($request, $connection);
    }

    /**
     * Parse a delivery status webhook.
     */
    public function parseStatusWebhook(Request $request, ProviderConnection $connection): array
    {
        return $this->parseTwilioStatus($request);
    }

    /**
     * Parse an inbound SMS message.
     */
    public function parseInbound(Request $request, ProviderConnection $connection): ?InboundMessage
    {
        return $this->parseTwilioInbound($request);
    }
}
