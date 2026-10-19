<?php

namespace Amacraft\MarketingAutomation\Contracts\Providers;

use Amacraft\MarketingAutomation\DTOs\WhatsAppTemplateData;
use Amacraft\MarketingAutomation\Models\ProviderConnection;

interface WhatsAppTemplateSource
{
    /**
     * Pull the message templates approved on the provider side.
     *
     * @return array<int, WhatsAppTemplateData>
     */
    public function fetchTemplates(ProviderConnection $connection): array;
}
