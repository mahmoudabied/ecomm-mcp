<?php

namespace Amacraft\Klaviyo\Services;

use Illuminate\Http\Client\ConnectionException;
use Throwable;
use Amacraft\Klaviyo\DTOs\ApiOutcome;
use Amacraft\Klaviyo\Models\OutboxItem;
use Amacraft\Klaviyo\Repositories\OutboxItemRepository;

class FlushOutbox
{
    /**
     * How many due rows one pass claims.
     */
    protected const CHUNK = 200;

    /**
     * Minutes after which a stuck `sending` row is retried.
     */
    protected const STALE_MINUTES = 10;

    /**
     * The attempt ceiling before a row is given up.
     */
    protected const MAX_ATTEMPTS = 10;

    /**
     * The exponential backoff ceiling in minutes.
     */
    protected const BACKOFF_CAP_MINUTES = 60;

    /**
     * Create a new flush service instance.
     */
    public function __construct(
        protected OutboxItemRepository $outboxItems,
    ) {}

    /**
     * Flush every due row of one connection.
     */
    public function flush(int $connectionId): void
    {
        $this->outboxItems->releaseStale(self::STALE_MINUTES);

        foreach ($this->outboxItems->claimDueFor($connectionId, self::CHUNK) as $item) {
            $this->send($item);
        }
    }

    /**
     * Send one row and record the result.
     */
    public function send(OutboxItem $item): void
    {
        $item->forceFill(['status' => 'sending'])->save();

        $outcome = $this->deliver($item);

        if ($outcome->succeeded) {
            $item->forceFill([
                'status'     => 'sent',
                'sent_at'    => now(),
                'last_error' => null,
            ])->save();

            return;
        }

        $this->recordFailure($item, $outcome);
    }

    /**
     * Deliver one row by its kind.
     */
    protected function deliver(OutboxItem $item): ApiOutcome
    {
        $connection = $item->connection()->first();

        if ($connection === null || ! $connection->isActive()) {
            return ApiOutcome::permanent('Connection is not active.');
        }

        $client = new KlaviyoClient($connection);

        try {
            return match ($item->kind) {
                'event'          => $client->createEvent($item->payload['data']['attributes'] ?? []),
                'profile'        => $client->importProfile($item->payload['data']['attributes'] ?? []),
                'catalog_item'   => $client->upsertCatalogItem($item->payload),
                'catalog_delete' => $client->deleteCatalogItem((string) ($item->payload['external_id'] ?? '')),
                'subscription'   => $this->deliverSubscription($client, $item),
                default          => ApiOutcome::permanent("Unknown outbox kind [{$item->kind}]."),
            };
        } catch (ConnectionException $exception) {
            return $client->outcome($exception);
        } catch (Throwable $exception) {
            return ApiOutcome::retryable($exception->getMessage());
        }
    }

    /**
     * Park a failed row for retry or give up on it.
     */
    protected function recordFailure(OutboxItem $item, ApiOutcome $outcome): void
    {
        $attempts = $item->attempts + 1;

        if ($outcome->retryable && $attempts < self::MAX_ATTEMPTS) {
            $item->forceFill([
                'status'       => 'pending',
                'attempts'     => $attempts,
                'available_at' => now()->addMinutes(min(2 ** $attempts, self::BACKOFF_CAP_MINUTES)),
                'last_error'   => $outcome->error,
            ])->save();

            return;
        }

        $item->forceFill([
            'status'     => 'failed',
            'attempts'   => $attempts,
            'last_error' => $outcome->error,
        ])->save();
    }

    /**
     * Deliver a subscription row, falling back to an event when no list is set.
     */
    protected function deliverSubscription(KlaviyoClient $client, OutboxItem $item): ApiOutcome
    {
        $connection = $item->connection;
        $payload = $item->payload;

        $listId = match ($payload['channel'] ?? 'email') {
            'sms'   => $connection->sms_list_id,
            default => $connection->list_id,
        };

        if (! $listId) {
            return $client->createEvent($payload['event_attributes'] ?? []);
        }

        return $payload['subscribed'] ?? true
            ? $client->subscribeProfiles($listId, [$payload['profile']], $payload['channel'] ?? 'email')
            : $client->unsubscribeProfiles($listId, [$payload['profile']], $payload['channel'] ?? 'email');
    }
}
