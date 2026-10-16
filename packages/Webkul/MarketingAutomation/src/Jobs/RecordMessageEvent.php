<?php

namespace Webkul\MarketingAutomation\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Event;
use Spatie\Multitenancy\Jobs\NotTenantAware;
use Webkul\MarketingAutomation\Enums\MessageEventType;
use Webkul\MarketingAutomation\Enums\MessageStatus;
use Webkul\MarketingAutomation\Models\MessageEvent;
use Webkul\MarketingAutomation\Models\MessageLog;
use Webkul\MarketingAutomation\Services\StatsRecorder;

class RecordMessageEvent implements NotTenantAware, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    /**
     * Status ranks used to keep the log's status moving forward only.
     */
    protected const STATUS_RANKS = [
        'queued'    => 0,
        'sent'      => 1,
        'delivered' => 2,
        'read'      => 3,
        'failed'    => 4,
        'bounced'   => 5,
    ];

    /**
     * The journey stats incremented on the first occurrence of an event type.
     */
    protected const STATS_METRICS = [
        'delivered'    => 'delivered',
        'opened'       => 'opened',
        'clicked'      => 'clicked',
        'unsubscribed' => 'unsubscribed',
        'failed'       => 'failed',
    ];

    /**
     * The first-time timestamp each event type sets on the log.
     */
    protected const TIMESTAMP_FIELDS = [
        'delivered' => 'delivered_at',
        'opened'    => 'opened_at',
        'clicked'   => 'clicked_at',
    ];

    /**
     * Create a new job instance.
     *
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        protected string $messageUuid,
        protected MessageEventType $type,
        protected array $meta = [],
    ) {
        $this->onQueue('automation');
    }

    /**
     * Record the event, advance the log state and count first occurrences.
     */
    public function handle(StatsRecorder $statsRecorder): void
    {
        $log = MessageLog::query()->where('uuid', $this->messageUuid)->first();

        if ($log === null) {
            return;
        }

        $isFirst = ! MessageEvent::query()
            ->where('message_log_id', $log->id)
            ->where('type', $this->type->value)
            ->exists();

        MessageEvent::query()->create([
            'message_log_id' => $log->id,
            'type'           => $this->type->value,
            'url'            => $this->meta['url'] ?? null,
            'meta'           => Arr::except($this->meta, ['url']),
            'occurred_at'    => now(),
        ]);

        $this->updateLog($log);

        if ($isFirst && isset(self::STATS_METRICS[$this->type->value])) {
            $statsRecorder->increment(
                (int) $log->flow_id,
                (string) $log->step_key,
                (int) $log->channel_id,
                self::STATS_METRICS[$this->type->value],
            );
        }

        Event::dispatch('marketing_automation.message.status.after', [$log->refresh(), $this->type]);
    }

    /**
     * Set the first-time timestamp and move the status forward, never back.
     */
    protected function updateLog(MessageLog $log): void
    {
        $attributes = [];

        $timestampField = self::TIMESTAMP_FIELDS[$this->type->value] ?? null;

        if ($timestampField !== null && $log->{$timestampField} === null) {
            $attributes[$timestampField] = now();
        }

        if ($this->type === MessageEventType::Failed && ! empty($this->meta['error_code'])) {
            $attributes['error_code'] = $this->meta['error_code'];
            $attributes['error_message'] = $this->meta['error_message'] ?? null;
        }

        $status = $this->statusFor($log);

        if ($status !== null) {
            $attributes['status'] = $status->value;
        }

        if ($attributes !== []) {
            $log->update($attributes);
        }
    }

    /**
     * The new status of the log, or null when this event type does not move it.
     */
    protected function statusFor(MessageLog $log): ?MessageStatus
    {
        $status = match ($this->type) {
            MessageEventType::Delivered => MessageStatus::Delivered,
            MessageEventType::Read      => MessageStatus::Read,
            MessageEventType::Failed    => MessageStatus::Failed,
            MessageEventType::Bounced   => MessageStatus::Bounced,
            default                     => null,
        };

        if (
            $status === null
            || self::STATUS_RANKS[$status->value] <= self::STATUS_RANKS[$log->status->value]
        ) {
            return null;
        }

        return $status;
    }
}
