<?php

namespace Webkul\MarketingAutomation\Services;

use Webkul\MarketingAutomation\Enums\MessageChannel;
use Webkul\MarketingAutomation\Models\MessageLog;

class UnsubscribeLink
{
    /**
     * Create a new unsubscribe link instance.
     */
    public function __construct(
        protected SignedToken $signedToken,
    ) {}

    /**
     * The one-click unsubscribe URL of a marketing email, or null for
     * transactional mail, which never carries one (FR-023).
     */
    public function forEmail(MessageLog $log, ?string $contactUuid): ?string
    {
        if (! $log->is_marketing) {
            return null;
        }

        return route('shop.automation.unsubscribe', $this->signedToken->make(
            (string) $contactUuid,
            MessageChannel::Email->value,
            (string) $log->uuid,
        ));
    }
}
