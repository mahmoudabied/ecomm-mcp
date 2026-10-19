<?php

namespace Amacraft\MarketingAutomation\Services;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use Amacraft\MarketingAutomation\DTOs\InboundMessage;
use Amacraft\MarketingAutomation\DTOs\StatusUpdate;
use Amacraft\MarketingAutomation\Enums\ConsentSource;
use Amacraft\MarketingAutomation\Enums\MessageChannel;
use Amacraft\MarketingAutomation\Enums\MessageEventType;
use Amacraft\MarketingAutomation\Models\Contact;
use Amacraft\MarketingAutomation\Models\MessageLog;
use Amacraft\MarketingAutomation\Models\ProviderConnection;
use Amacraft\MarketingAutomation\Repositories\ProviderConnectionRepository;

class WebhookProcessor
{
    /**
     * Create a new processor instance.
     */
    public function __construct(
        protected ProviderConnectionRepository $providerConnectionRepository,
        protected ConsentService $consentService,
        protected MessageEvents $messageEvents,
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

            $type = $this->eventTypeFor($update->status);

            if ($type === null) {
                continue;
            }

            $this->messageEvents->record((string) $log->uuid, $type, [
                'error_code'    => $update->errorCode,
                'error_message' => $update->errorMessage !== null ? Str::limit($update->errorMessage, 250) : null,
            ]);
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
     * Map a provider status onto the message event type it records; unknown
     * statuses are silently dropped.
     */
    protected function eventTypeFor(string $status): ?MessageEventType
    {
        return match (strtolower($status)) {
            'sent'      => MessageEventType::Sent,
            'delivered' => MessageEventType::Delivered,
            'read'      => MessageEventType::Read,
            'failed'    => MessageEventType::Failed,
            'bounced'   => MessageEventType::Bounced,
            default     => null,
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
