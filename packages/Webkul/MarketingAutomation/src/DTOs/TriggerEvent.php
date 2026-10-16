<?php

namespace Webkul\MarketingAutomation\DTOs;

use Webkul\MarketingAutomation\Enums\TriggerType;

final readonly class TriggerEvent
{
    public function __construct(
        public TriggerType $type,
        public int $channelId,
        public ?int $customerId,
        public ?string $email,
        public ?string $phone,
        public string $triggerKey,
        public array $payload,
        public ?string $locale = null,
    ) {}
}
