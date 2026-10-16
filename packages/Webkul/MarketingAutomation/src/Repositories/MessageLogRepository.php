<?php

namespace Webkul\MarketingAutomation\Repositories;

use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Webkul\Core\Eloquent\Repository;
use Webkul\MarketingAutomation\Enums\MessageEventType;
use Webkul\MarketingAutomation\Enums\MessageStatus;

class MessageLogRepository extends Repository
{
    /**
     * Specify model class name.
     */
    public function model(): string
    {
        return 'Webkul\MarketingAutomation\Contracts\MessageLog';
    }

    /**
     * The contact's live marketing messages on the channel since the given time, oldest first.
     * The enrolment's own queued message is left out, so a retried send never caps itself.
     */
    public function marketingSentSince(int $contactId, string $messageChannel, CarbonInterface $since, ?int $enrolmentId = null): Collection
    {
        return $this->model
            ->where('contact_id', $contactId)
            ->where('message_channel', $messageChannel)
            ->where('is_marketing', true)
            ->where('is_test', false)
            ->whereIn('status', [
                MessageStatus::Queued->value,
                MessageStatus::Sent->value,
                MessageStatus::Delivered->value,
                MessageStatus::Read->value,
            ])
            ->where('created_at', '>=', $since)
            ->when($enrolmentId, fn ($query) => $query->where(fn ($query) => $query
                ->where('status', '!=', MessageStatus::Queued->value)
                ->orWhereNull('enrolment_id')
                ->orWhere('enrolment_id', '!=', $enrolmentId)))
            ->orderBy('created_at')
            ->get(['id', 'created_at']);
    }

    /**
     * Record a lifecycle event on the message with this uuid, when it exists.
     *
     * @param  array<string, mixed>  $meta
     */
    public function recordEvent(string $messageUuid, MessageEventType $type, array $meta = []): void
    {
        $this->model
            ->where('uuid', $messageUuid)
            ->first()
            ?->events()
            ->create([
                'type'        => $type->value,
                'meta'        => $meta,
                'occurred_at' => now(),
            ]);
    }
}
