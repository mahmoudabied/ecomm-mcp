<?php

namespace Webkul\MarketingAutomation\Contracts\Providers;

use Illuminate\Http\Request;
use Webkul\MarketingAutomation\DTOs\OutboundMessage;
use Webkul\MarketingAutomation\DTOs\SendResult;
use Webkul\MarketingAutomation\DTOs\StatusUpdate;
use Webkul\MarketingAutomation\Models\ProviderConnection;

interface MessagingProvider
{
    /**
     * Credential field schema shown in the admin form.
     *
     * @return array<int, array{name: string, label: string, type: 'text'|'password'|'select', secret: bool, required: bool, options?: array<string, string>}>
     */
    public static function fields(): array;

    /**
     * Send the message and classify the outcome.
     */
    public function send(ProviderConnection $connection, OutboundMessage $message): SendResult;

    /**
     * Send a harmless probe message to verify the credentials.
     */
    public function testConnection(ProviderConnection $connection, string $to): SendResult;

    /**
     * Verify the signature of an inbound webhook request.
     */
    public function verifyWebhook(Request $request, ProviderConnection $connection): bool;

    /**
     * Turn a status webhook into delivery updates.
     *
     * @return array<int, StatusUpdate>
     */
    public function parseStatusWebhook(Request $request, ProviderConnection $connection): array;
}
