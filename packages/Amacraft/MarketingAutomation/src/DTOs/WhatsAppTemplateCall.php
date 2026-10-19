<?php

namespace Amacraft\MarketingAutomation\DTOs;

final readonly class WhatsAppTemplateCall
{
    /**
     * @param  array<int, string>  $bodyParams
     * @param  array<int, string>  $buttonParams
     */
    public function __construct(
        public string $name,
        public string $language,
        public array $bodyParams = [],
        public array $buttonParams = [],
        public ?string $headerMediaUrl = null,
    ) {}
}
