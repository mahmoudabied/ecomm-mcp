<?php

namespace Webkul\MarketingAutomation\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Webkul\MarketingAutomation\Contracts\Providers\SupportsInboundOptOut;
use Webkul\MarketingAutomation\Models\ProviderConnection;
use Webkul\MarketingAutomation\Services\ProviderManager;
use Webkul\MarketingAutomation\Services\WebhookProcessor;

class WebhookController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct(
        protected ProviderManager $providerManager,
        protected WebhookProcessor $processor,
    ) {}

    /**
     * Answer a Meta subscription handshake; other drivers use GET for
     * delivery reports, so their GET requests are processed like POSTs.
     */
    public function verify(Request $request, string $driver, string $uuid): Response
    {
        $connection = $this->findConnection($driver, $uuid);

        if ($connection === null) {
            abort(404);
        }

        if ($driver === 'meta_cloud') {
            $token = (string) $request->query('hub_verify_token');

            if ($token === '' || ! hash_equals($connection->webhook_secret, $token)) {
                abort(403);
            }

            return response((string) $request->query('hub_challenge'));
        }

        return $this->process($request, $connection);
    }

    /**
     * Accept a provider webhook, verify it and record the delivery updates.
     */
    public function receive(Request $request, string $driver, string $uuid): Response
    {
        $connection = $this->findConnection($driver, $uuid);

        if ($connection === null) {
            abort(404);
        }

        return $this->process($request, $connection);
    }

    /**
     * Verify the request and hand its payload to the webhook processor.
     */
    protected function process(Request $request, ProviderConnection $connection): Response
    {
        if (! $this->providerManager->driver($connection)->verifyWebhook($request, $connection)) {
            abort(403);
        }

        $driver = $this->providerManager->driver($connection);

        $this->processor->processStatusUpdates(
            $connection,
            $driver->parseStatusWebhook($request, $connection),
        );

        if ($driver instanceof SupportsInboundOptOut) {
            $this->processor->processInbound(
                $connection,
                $driver->parseInbound($request, $connection),
            );
        }

        return response('');
    }

    /**
     * Find a connection by its uuid and driver.
     */
    protected function findConnection(string $driver, string $uuid): ?ProviderConnection
    {
        return ProviderConnection::query()
            ->where('uuid', $uuid)
            ->where('driver', $driver)
            ->first();
    }
}
