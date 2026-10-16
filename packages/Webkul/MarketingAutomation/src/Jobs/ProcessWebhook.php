<?php

namespace Webkul\MarketingAutomation\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Request;
use Illuminate\Queue\InteractsWithQueue;
use Spatie\Multitenancy\Jobs\NotTenantAware;
use Webkul\MarketingAutomation\Contracts\ProviderConnection;
use Webkul\MarketingAutomation\Contracts\Providers\SupportsInboundOptOut;
use Webkul\MarketingAutomation\Repositories\ProviderConnectionRepository;
use Webkul\MarketingAutomation\Services\ProviderManager;
use Webkul\MarketingAutomation\Services\WebhookProcessor;

class ProcessWebhook implements NotTenantAware, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    /**
     * Create a new job instance.
     *
     * @param  array<string, mixed>  $captured
     */
    public function __construct(
        protected string $driver,
        protected int $providerConnectionId,
        protected array $captured,
    ) {
        $this->onQueue('automation');
    }

    /**
     * Handle the captured webhook payload.
     */
    public function handle(
        ProviderConnectionRepository $providerConnectionRepository,
        ProviderManager $providerManager,
        WebhookProcessor $processor,
    ): void {
        $connection = $providerConnectionRepository->find($this->providerConnectionId);

        if (
            $connection === null
            || $connection->driver !== $this->driver
        ) {
            return;
        }

        $driver = $providerManager->driver($connection);

        $request = $this->rebuildRequest();

        $this->applyStatusUpdates($processor, $connection, $driver, $request);

        if ($driver instanceof SupportsInboundOptOut) {
            $processor->processInbound(
                $connection,
                $driver->parseInbound($request, $connection),
            );
        }
    }

    /**
     * Record the delivery receipts; full status handling lands with the tracking story (T116).
     */
    protected function applyStatusUpdates(
        WebhookProcessor $processor,
        ProviderConnection $connection,
        object $driver,
        Request $request,
    ): void {
        $processor->processStatusUpdates(
            $connection,
            $driver->parseStatusWebhook($request, $connection),
        );
    }

    /**
     * Rebuild the request from the captured parts; Request::create never parses the raw body, so the input is passed.
     */
    protected function rebuildRequest(): Request
    {
        return Request::create(
            (string) ($this->captured['uri'] ?? '/'),
            (string) ($this->captured['method'] ?? 'POST'),
            (array) ($this->captured['input'] ?? []),
            [],
            [],
            $this->serverHeaders((array) ($this->captured['headers'] ?? [])),
            (string) ($this->captured['body'] ?? ''),
        );
    }

    /**
     * Turn header names back into the $_SERVER form; Symfony reads the content headers without the HTTP_ prefix.
     *
     * @param  array<string, mixed>  $headers
     * @return array<string, mixed>
     */
    protected function serverHeaders(array $headers): array
    {
        $server = [];

        foreach ($headers as $key => $values) {
            $value = is_array($values) ? reset($values) : $values;

            $name = strtoupper(str_replace('-', '_', (string) $key));

            $server[str_starts_with($name, 'CONTENT_') ? $name : 'HTTP_'.$name] = $value;
        }

        return $server;
    }
}
