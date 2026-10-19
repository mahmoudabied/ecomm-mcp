<?php

namespace Amacraft\MarketingAutomation\DTOs;

use Amacraft\MarketingAutomation\Enums\SendStatus;

final readonly class SendResult
{
    public function __construct(
        public SendStatus $status,
        public ?string $providerMessageId = null,
        public ?string $errorCode = null,
        public ?string $errorMessage = null,
    ) {}

    /**
     * Build a result for a message the provider accepted.
     */
    public static function accepted(string $id): self
    {
        return new self(status: SendStatus::Accepted, providerMessageId: $id);
    }

    /**
     * Build a result for a temporary failure that can be retried.
     */
    public static function retryable(string $code, string $message): self
    {
        return new self(status: SendStatus::RetryableError, errorCode: $code, errorMessage: $message);
    }

    /**
     * Build a result for a permanent failure that must not be retried.
     */
    public static function permanent(string $code, string $message): self
    {
        return new self(status: SendStatus::PermanentError, errorCode: $code, errorMessage: $message);
    }
}
