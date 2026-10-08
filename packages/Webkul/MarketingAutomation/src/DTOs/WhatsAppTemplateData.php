<?php

namespace Webkul\MarketingAutomation\DTOs;

final readonly class WhatsAppTemplateData
{
    public function __construct(
        public string $externalId,
        public string $name,
        public string $language,
        public string $category,
        public string $status,
        public array $components,
        public int $variableCount,
    ) {}
}
