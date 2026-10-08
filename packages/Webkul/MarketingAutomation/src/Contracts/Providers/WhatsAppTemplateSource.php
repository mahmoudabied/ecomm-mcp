<?php

namespace Webkul\MarketingAutomation\Contracts\Providers;

use Webkul\MarketingAutomation\DTOs\WhatsAppTemplateData;
use Webkul\MarketingAutomation\Models\ProviderConnection;

interface WhatsAppTemplateSource
{
    /**
     * Pull the message templates approved on the provider side.
     *
     * @return array<int, WhatsAppTemplateData>
     */
    public function fetchTemplates(ProviderConnection $connection): array;
}
