<?php

namespace Webkul\MarketingAutomation\DTOs;

use Carbon\CarbonImmutable;

final readonly class StatusUpdate
{
    public function __construct(
        public string $providerMessageId,
        public string $status,
        public ?string $errorCode,
        public ?string $errorMessage,
        public CarbonImmutable $occurredAt,
    ) {}
}
