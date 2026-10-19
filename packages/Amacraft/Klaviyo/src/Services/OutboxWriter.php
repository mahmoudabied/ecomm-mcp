<?php

namespace Amacraft\Klaviyo\Services;

use Amacraft\Klaviyo\Jobs\FlushOutboxBatch;
use Amacraft\Klaviyo\Models\Connection;
use Amacraft\Klaviyo\Repositories\OutboxItemRepository;

class OutboxWriter
{
    /**
     * Create a new outbox writer instance.
     */
    public function __construct(
        protected OutboxItemRepository $outboxItems,
    ) {}

    /**
     * Idempotently queue one payload and nudge the flusher.
     */
    public function write(Connection $connection, string $kind, string $uniqueId, array $payload): void
    {
        if ($this->outboxItems->writeOrIgnore($connection->id, $kind, $uniqueId, $payload) !== null) {
            $this->queueFlush($connection->id);
        }
    }

    /**
     * Write every piece produced by the maker for each active connection of
     * a sales channel, then nudge the flusher once per connection. The maker
     * returns a list of [kind, unique_id, payload].
     *
     * @param  callable(Connection): array<int, array{0: string, 1: string, 2: array}>  $make
     */
    public function writeToChannel(int $channelId, callable $make): void
    {
        foreach ($this->activeConnectionsOf($channelId) as $connection) {
            $written = false;

            foreach ($make($connection) as [$kind, $uniqueId, $payload]) {
                $written = $this->outboxItems->writeOrIgnore($connection->id, $kind, $uniqueId, $payload) !== null
                    || $written;
            }

            if ($written) {
                $this->queueFlush($connection->id);
            }
        }
    }

    /**
     * Queue one flush pass for a connection after the current transaction.
     */
    protected function queueFlush(int $connectionId): void
    {
        FlushOutboxBatch::dispatch($connectionId)
            ->afterCommit()
            ->onQueue('klaviyo');
    }

    /**
     * All connected connections serving a channel.
     *
     * @return array<int, Connection>
     */
    public function activeConnectionsOf(int $channelId): array
    {
        return Connection::query()
            ->where('channel_id', $channelId)
            ->where('status', 'connected')
            ->get()
            ->all();
    }
}
