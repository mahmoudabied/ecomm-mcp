<?php

namespace Webkul\MarketingAutomation\Enums;

enum WhatsAppTemplateStatus: string
{
    case Approved = 'approved';

    case Pending = 'pending';

    case Rejected = 'rejected';

    case Paused = 'paused';

    case Disabled = 'disabled';

    /**
     * Map a provider approval status (Meta or Twilio, any case) onto a local
     * status. Deleted templates count as disabled; review states the table
     * does not know (received, unsubmitted, in_appeal…) count as pending.
     */
    public static function fromProvider(string $status): self
    {
        $status = strtolower(trim($status));

        if (in_array($status, ['deleted', 'pending_deletion'], true)) {
            return self::Disabled;
        }

        return self::tryFrom($status) ?? self::Pending;
    }
}
