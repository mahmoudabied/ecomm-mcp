<?php

namespace Amacraft\MarketingAutomation\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Amacraft\MarketingAutomation\Jobs\ProcessWebhook;
use Amacraft\MarketingAutomation\Models\ProviderConnection;
use Amacraft\MarketingAutomation\Services\ProviderManager;

class WebhookController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct(
        protected ProviderManager $providerManager,
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

            if (
                $token === ''
                || ! hash_equals($connection->webhook_secret, $token)
            ) {
                abort(403);
            }

            return response((string) $request->query('hub_challenge'));
        }

        return $this->process($request, $connection);
    }

    /**
     * Accept a provider webhook, verify it and queue the processing.
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
     * Verify the request and hand its payload to the queue; providers get
     * their 200 immediately, the payload is processed on the queue.
     */
    protected function process(Request $request, ProviderConnection $connection): Response
    {
        if (! $this->providerManager->driver($connection)->verifyWebhook($request, $connection)) {
            abort(403);
        }

        ProcessWebhook::dispatch($connection->driver, $connection->id, [
            'method'  => $request->getMethod(),
            'uri'     => $request->getRequestUri(),
            'query'   => $request->query->all(),
            'input'   => $request->input(),
            'headers' => $request->headers->all(),
            'body'    => $request->getContent(),
        ]);

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
