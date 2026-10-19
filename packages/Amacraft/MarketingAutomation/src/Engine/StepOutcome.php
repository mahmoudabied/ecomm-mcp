<?php

namespace Amacraft\MarketingAutomation\Engine;

use Carbon\CarbonInterface;

class StepOutcome
{
    /**
     * Create a new step outcome instance.
     */
    private function __construct(
        public readonly string $kind,
        public readonly ?string $nextKey = null,
        public readonly ?CarbonInterface $waitUntil = null,
        public readonly ?bool $branch = null,
        public readonly ?string $reason = null,
    ) {}

    /**
     * Move to the given step, or finish the journey when null.
     */
    public static function next(?string $key): self
    {
        return new self(kind: 'next', nextKey: $key);
    }

    /**
     * Pause until a moment in time, then continue at the given key.
     */
    public static function waitUntil(CarbonInterface $at, ?string $nextKey = null): self
    {
        return new self(kind: 'wait', waitUntil: $at, nextKey: $nextKey);
    }

    /**
     * Defer a blocked send until a moment in time, then retry the same step.
     */
    public static function deferUntil(CarbonInterface $at, ?string $nextKey = null, ?string $reason = null): self
    {
        return new self(kind: 'defer', waitUntil: $at, nextKey: $nextKey, reason: $reason);
    }

    /**
     * Take the yes or no branch of a condition step.
     */
    public static function branch(bool $yes): self
    {
        return new self(kind: 'branch', branch: $yes);
    }

    /**
     * End the journey successfully.
     */
    public static function exit(): self
    {
        return new self(kind: 'exit');
    }

    /**
     * Release the job so the same step can be retried after the backoff.
     */
    public static function retry(int $delaySeconds): self
    {
        return new self(kind: 'retry', waitUntil: now()->addSeconds($delaySeconds));
    }
}
