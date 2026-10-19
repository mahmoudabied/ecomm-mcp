<?php

namespace Amacraft\MarketingAutomation\Jobs;

use Amacraft\MarketingAutomation\Contracts\ProviderConnection;
use Amacraft\MarketingAutomation\Contracts\Providers\SupportsInboundOptOut;
use Amacraft\MarketingAutomation\Repositories\ProviderConnectionRepository;
use Amacraft\MarketingAutomation\Services\ProviderManager;
use Amacraft\MarketingAutomation\Services\WebhookProcessor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Request;
use Illuminate\Queue\InteractsWithQueue;

class ProcessWebhook implements ShouldQueue
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
        $this->onQueue(config('marketing-automation.queues.automation'));
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
