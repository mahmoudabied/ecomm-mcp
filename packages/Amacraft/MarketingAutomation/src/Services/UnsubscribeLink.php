<?php

namespace Amacraft\MarketingAutomation\Services;

use Amacraft\MarketingAutomation\Enums\MessageChannel;
use Amacraft\MarketingAutomation\Models\MessageLog;
use Amacraft\MarketingAutomation\Rendering\ChannelUrl;
use Webkul\Core\Models\Channel;

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
     * The one-click unsubscribe URL of a marketing email, or null for
     * transactional mail, which never carries one (FR-023). The link lives on
     * the message's own channel, so non-default stores point at their host.
     */
    public function forEmail(MessageLog $log, ?string $contactUuid): ?string
    {
        if (! $log->is_marketing) {
            return null;
        }

        $channel = Channel::query()->find($log->channel_id)
            ?? core()->getCurrentChannel();

        return $this->channelUrl->to('shop.automation.unsubscribe', $channel, $this->signedToken->make(
            (string) $contactUuid,
            MessageChannel::Email->value,
            (string) $log->uuid,
        ));
    }
}
