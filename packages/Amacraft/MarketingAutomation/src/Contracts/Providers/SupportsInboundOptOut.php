<?php

namespace Amacraft\MarketingAutomation\Contracts\Providers;

use Illuminate\Http\Request;
use Amacraft\MarketingAutomation\DTOs\InboundMessage;
use Amacraft\MarketingAutomation\Models\ProviderConnection;

interface SupportsInboundOptOut
{
    /**
     * Extract an inbound message, or null when the payload is not one.
     */
    public function parseInbound(Request $request, ProviderConnection $connection): ?InboundMessage;
}
