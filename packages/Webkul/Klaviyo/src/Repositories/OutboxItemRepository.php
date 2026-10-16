<?php

namespace Webkul\Klaviyo\Repositories;

use Illuminate\Database\UniqueConstraintViolationException;
use Webkul\Core\Eloquent\Repository;
use Webkul\Klaviyo\Contracts\OutboxItem;

class OutboxItemRepository extends Repository
{
    /**
     * Specify model class name.
     */
    public function model(): string
    {
        return 'Webkul\Klaviyo\Contracts\OutboxItem';
    }

    /**
     * Idempotently queue a row; duplicates of the unique id are dropped.
     */
    public function writeOrIgnore(int $connectionId, string $kind, string $uniqueId, array $payload): ?OutboxItem
    {
        try {
            return $this->model->create([
                'connection_id' => $connectionId,
                'kind'          => $kind,
                'unique_id'     => substr($uniqueId, 0, 191),
                'payload'       => $payload,
                'status'        => 'pending',
            ]);
        } catch (UniqueConstraintViolationException) {
            return null;
        }
    }

    /**
     * Claim due pending rows of one connection for sending.
     *
     * @return array<int, OutboxItem>
     */
    public function claimDueFor(int $connectionId, int $limit): array
    {
        return $this->model
            ->query()
            ->where('connection_id', $connectionId)
            ->where('status', 'pending')
            ->where('available_at', '<=', now())
            ->orderBy('id')
            ->limit($limit)
            ->get()
            ->all();
    }

    /**
     * Requeue rows stuck in `sending` for more than the given minutes.
     */
    public function releaseStale(int $minutes): int
    {
        return $this->model
            ->query()
            ->where('status', 'sending')
            ->where('updated_at', '<', now()->subMinutes($minutes))
            ->update(['status' => 'pending']);
    }

    /**
     * Requeue failed rows so the next flush tries them again.
     */
    public function retryFailed(int $connectionId): int
    {
        return $this->model
            ->query()
            ->where('connection_id', $connectionId)
            ->where('status', 'failed')
            ->update([
                'status'       => 'pending',
                'attempts'     => 0,
                'available_at' => now(),
            ]);
    }

    /**
     * Outbox counters for the admin dashboard.
     *
     * @return array{pending: int, failed: int, sent_24h: int}
     */
    public function stats(int $connectionId): array
    {
        return [
            'pending'  => $this->countByStatus($connectionId, 'pending'),
            'failed'   => $this->countByStatus($connectionId, 'failed'),
            'sent_24h' => $this->model
                ->where('connection_id', $connectionId)
                ->where('status', 'sent')
                ->where('sent_at', '>=', now()->subDay())
                ->count(),
        ];
    }

    /**
     * Delete delivered rows older than the given days.
     */
    public function purgeSent(int $days): int
    {
        return $this->model
            ->query()
            ->where('status', 'sent')
            ->where('sent_at', '<', now()->subDays($days))
            ->delete();
    }

    /**
     * Count the rows of one connection in a status.
     */
    protected function countByStatus(int $connectionId, string $status): int
    {
        return $this->model
            ->where('connection_id', $connectionId)
            ->where('status', $status)
            ->count();
    }
}
