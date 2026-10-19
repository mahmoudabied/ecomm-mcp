<?php

namespace Amacraft\MarketingAutomation\Services;

use Amacraft\MarketingAutomation\Enums\MessageChannel;
use Amacraft\MarketingAutomation\Models\MessageLog;
use Amacraft\MarketingAutomation\Rendering\ChannelUrl;

class UnsubscribeLink
{
    /**
     * Create a new unsubscribe link instance.
     */
    public function __construct(
        protected SignedToken $signedToken,
        protected ChannelUrl $channelUrl,
    ) {}

    /**
     * The one-click unsubscribe URL of a marketing email on its own channel host;
     * null for transactional mail, which never carries one (FR-023).
     */
    public function forEmail(MessageLog $log, ?string $contactUuid): ?string
    {
        if (! $log->is_marketing) {
            return null;
        }

        return $this->channelUrl->to('shop.automation.unsubscribe', $this->channelUrl->channel($log->channel_id), $this->signedToken->make(
            (string) $contactUuid,
            MessageChannel::Email->value,
            (string) $log->uuid,
        ));
    }
}
