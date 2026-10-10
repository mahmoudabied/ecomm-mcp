<?php

namespace Webkul\MarketingAutomation\Services;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use Webkul\MarketingAutomation\DTOs\InboundMessage;
use Webkul\MarketingAutomation\DTOs\StatusUpdate;
use Webkul\MarketingAutomation\Enums\ConsentSource;
use Webkul\MarketingAutomation\Enums\MessageChannel;
use Webkul\MarketingAutomation\Enums\MessageEventType;
use Webkul\MarketingAutomation\Enums\MessageStatus;
use Webkul\MarketingAutomation\Models\Contact;
use Webkul\MarketingAutomation\Models\MessageEvent;
use Webkul\MarketingAutomation\Models\MessageLog;
use Webkul\MarketingAutomation\Models\ProviderConnection;
use Webkul\MarketingAutomation\Repositories\ProviderConnectionRepository;

class WebhookProcessor
{
    /**
     * Create a new processor instance.
     */
    public function __construct(
        protected ProviderConnectionRepository $providerConnectionRepository,
        protected ConsentService $consentService,
    ) {}

    /**
     * Record delivery status updates against the matching message logs.
     *
     * @param  array<int, StatusUpdate>  $updates
     */
    public function processStatusUpdates(ProviderConnection $connection, array $updates): void
    {
        foreach ($updates as $update) {
            $log = MessageLog::query()
                ->where('provider_connection_id', $connection->id)
                ->where('provider_message_id', $update->providerMessageId)
                ->first();

            if ($log === null) {
                continue;
            }

            $this->applyStatus($log, $update);
        }
    }

    /**
     * Handle an inbound message; an opt-out keyword revokes consent.
     */
    public function processInbound(ProviderConnection $connection, ?InboundMessage $message): void
    {
        if ($message === null) {
            return;
        }

        if (! $this->isOptOut($message->body)) {
            return;
        }

        $channel = $connection->message_channel;

        $source = $channel === MessageChannel::Whatsapp
            ? ConsentSource::WhatsappOptout
            : ConsentSource::SmsStop;

        foreach ($this->contactsFor($connection, $message->from) as $contact) {
            $this->consentService->revoke($contact, $channel, $source);
        }
    }

    /**
     * Apply one provider status onto the log and append the event.
     */
    protected function applyStatus(MessageLog $log, StatusUpdate $update): void
    {
        $status = MessageStatus::tryFrom($update->status);

        if ($status === null || $log->status === $status) {
            return;
        }

        $attributes = ['status' => $status->value];

        if ($status === MessageStatus::Delivered) {
            $attributes['delivered_at'] = $update->occurredAt;
        }

        if ($status === MessageStatus::Failed) {
            $attributes['error_code'] = $update->errorCode;
            $attributes['error_message'] = $update->errorMessage !== null ? Str::limit($update->errorMessage, 250) : null;
        }

        $log->update($attributes);

        MessageEvent::query()->create([
            'message_log_id' => $log->id,
            'type'           => $this->eventTypeFor($status)->value,
            'meta'           => array_filter([
                'error_code'    => $update->errorCode,
                'error_message' => $update->errorMessage !== null ? Str::limit($update->errorMessage, 250) : null,
            ]),
            'occurred_at'    => $update->occurredAt,
        ]);
    }

    /**
     * Map a message status onto its journey event type.
     */
    protected function eventTypeFor(MessageStatus $status): MessageEventType
    {
        return match ($status) {
            MessageStatus::Delivered => MessageEventType::Delivered,
            MessageStatus::Read      => MessageEventType::Read,
            MessageStatus::Failed    => MessageEventType::Failed,
            default                  => MessageEventType::Sent,
        };
    }

    /**
     * Whether an inbound message asks to stop receiving messages.
     */
    protected function isOptOut(string $body): bool
    {
        $keyword = strtoupper(trim($body));

        return in_array($keyword, ['STOP', 'STOP ALL', 'UNSUBSCRIBE', 'CANCEL', 'END', 'QUIT', 'إلغاء', 'الغاء', 'توقف'], true);
    }

    /**
     * Find the contacts behind an inbound message by their E.164 phone, scoped
     * to the sales channels where this connection is the default. A connection
     * that is no channel's default (e.g. used only by a flow override) matches
     * the phone across every sales channel.
     *
     * @return Collection<int, Contact>
     */
    protected function contactsFor(ProviderConnection $connection, string $from): Collection
    {
        $digits = preg_replace('/\D/', '', $from);

        if ($digits === '' || $digits === null) {
            return new Collection;
        }

        $channelIds = $this->providerConnectionRepository->defaultChannelIds($connection);

        return Contact::query()
            ->where('phone_e164', '+'.$digits)
            ->when($channelIds !== [], fn ($query) => $query->whereIn('channel_id', $channelIds))
            ->get();
    }
}
