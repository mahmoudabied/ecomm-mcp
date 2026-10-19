<?php

namespace Amacraft\MarketingAutomation\DTOs;

final readonly class OutboundMessage
{
    public function __construct(
        public string $to,
        public string $channel,
        public ?string $body,
        public ?WhatsAppTemplateCall $template,
        public string $messageUuid,
        public ?string $statusCallbackUrl = null,
    ) {}
}
