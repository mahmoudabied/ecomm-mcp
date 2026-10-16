<?php

namespace Webkul\MarketingAutomation\Services;

use Illuminate\Support\Facades\Cache;

class HealthCheck
{
    /**
     * The cache key holding the last scheduler heartbeat.
     */
    protected const HEARTBEAT_KEY = 'marketing_automation.scheduler_heartbeat';

    /**
     * Build the setup health report for the admin.
     *
     * @return array{queue_driver: string, queue_ok: bool, scheduler_last_seen_at: ?string, scheduler_ok: bool, mail_configured: bool, lag_over_5m_ratio: float}
     */
    public function report(): array
    {
        $lastSeenAt = Cache::get(self::HEARTBEAT_KEY);

        return [
            'queue_driver'           => (string) config('queue.default'),
            'queue_ok'               => config('queue.default') !== 'sync',
            'scheduler_last_seen_at' => $lastSeenAt ? (string) $lastSeenAt : null,
            'scheduler_ok'           => $this->schedulerOk($lastSeenAt),
            'mail_configured'        => ! in_array(config('mail.default'), ['log', 'array']),
            'lag_over_5m_ratio'      => $this->lagOver5mRatio(),
        ];
    }

    /**
     * Record a scheduler heartbeat, called by the dispatch command.
     */
    public function beat(): void
    {
        Cache::put(self::HEARTBEAT_KEY, now(), now()->addHours(1));
    }

    /**
     * Whether the scheduler was seen in the last five minutes.
     */
    protected function schedulerOk(mixed $lastSeenAt): bool
    {
        return $lastSeenAt !== null
            && now()->diffInMinutes($lastSeenAt) < 5;
    }

    /**
     * The share of dispatches that ran more than five minutes late.
     */
    protected function lagOver5mRatio(): float
    {
        $total = (int) Cache::get('marketing_automation.lag.total', 0);

        if ($total === 0) {
            return 0.0;
        }

        return (int) Cache::get('marketing_automation.lag.over_5m', 0) / $total;
    }
}
