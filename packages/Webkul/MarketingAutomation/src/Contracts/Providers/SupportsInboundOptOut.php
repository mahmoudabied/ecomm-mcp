<?php

namespace Webkul\MarketingAutomation\Contracts\Providers;

use Illuminate\Http\Request;
use Webkul\MarketingAutomation\DTOs\InboundMessage;
use Webkul\MarketingAutomation\Models\ProviderConnection;

interface SupportsInboundOptOut
{
    /**
     * Extract an inbound message, or null when the payload is not one.
     */
    public function parseInbound(Request $request, ProviderConnection $connection): ?InboundMessage;
}
