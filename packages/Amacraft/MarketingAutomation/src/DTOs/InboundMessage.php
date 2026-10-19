<?php

namespace Amacraft\MarketingAutomation\DTOs;

final readonly class InboundMessage
{
    public function __construct(
        public string $from,
        public string $body,
        public ?string $type = null,
    ) {}
}
