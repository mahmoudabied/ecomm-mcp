<?php

namespace Webkul\Klaviyo\DTOs;

final readonly class ApiOutcome
{
    /**
     * Create a new API outcome instance.
     */
    private function __construct(
        public bool $succeeded,
        public bool $retryable,
        public ?string $error,
    ) {}

    /**
     * A delivered request.
     */
    public static function success(): self
    {
        return new self(succeeded: true, retryable: false, error: null);
    }

    /**
     * A transient failure the caller should retry later.
     */
    public static function retryable(string $error): self
    {
        return new self(succeeded: false, retryable: true, error: $error);
    }

    /**
     * A failure that will not succeed on retry.
     */
    public static function permanent(string $error): self
    {
        return new self(succeeded: false, retryable: false, error: $error);
    }
}
