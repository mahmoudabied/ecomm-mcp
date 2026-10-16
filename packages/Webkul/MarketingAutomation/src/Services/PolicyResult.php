<?php

namespace Webkul\MarketingAutomation\Services;

use Carbon\CarbonInterface;
use Webkul\MarketingAutomation\Enums\SkipReason;

class PolicyResult
{
    /**
     * Create a new policy result instance.
     */
    public function __construct(
        public readonly bool $allowed,
        public readonly ?SkipReason $reason = null,
        public readonly ?CarbonInterface $deferUntil = null,
        public readonly ?string $recipient = null,
    ) {}

    /**
     * A passing result with the resolved recipient.
     */
    public static function allow(string $recipient): self
    {
        return new self(allowed: true, recipient: $recipient);
    }

    /**
     * A result that blocks the send with a reason.
     */
    public static function skip(SkipReason $reason): self
    {
        return new self(allowed: false, reason: $reason);
    }

    /**
     * A result that blocks now but may be retried later.
     */
    public static function defer(SkipReason $reason, CarbonInterface $until): self
    {
        return new self(allowed: false, reason: $reason, deferUntil: $until);
    }
}
