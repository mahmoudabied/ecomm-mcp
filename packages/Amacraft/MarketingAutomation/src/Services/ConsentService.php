<?php

namespace Amacraft\MarketingAutomation\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Webkul\Core\Models\SubscribersList;
use Amacraft\MarketingAutomation\Enums\ConsentSource;
use Amacraft\MarketingAutomation\Enums\ConsentStatus;
use Amacraft\MarketingAutomation\Enums\MessageChannel;
use Amacraft\MarketingAutomation\Models\Consent;
use Amacraft\MarketingAutomation\Models\ConsentEvent;
use Amacraft\MarketingAutomation\Models\Contact;
use Amacraft\MarketingAutomation\Repositories\ConsentRepository;

class ConsentService
{
    /**
     * Create a new consent service instance.
     */
    public function __construct(
        protected ConsentRepository $consentRepository,
    ) {}

    /**
     * Grant consent for the given message channel and record the event.
     */
    public function grant(Contact $contact, MessageChannel $channel, ConsentSource $source, ?Request $request = null): Consent
    {
        return $this->record($contact, $channel, ConsentStatus::Granted, $source, $request);
    }

    /**
     * Revoke consent for the given message channel and record the event.
     */
    public function revoke(Contact $contact, MessageChannel $channel, ConsentSource $source, ?Request $request = null): Consent
    {
        $consent = $this->record($contact, $channel, ConsentStatus::Revoked, $source, $request);

        if ($channel === MessageChannel::Email) {
            $this->unsubscribeFromNewsletter($contact);
        }

        return $consent;
    }

    /**
     * Get the current consent status, defaulting to none.
     */
    public function status(Contact $contact, MessageChannel $channel): ConsentStatus
    {
        return Consent::query()
            ->where('contact_id', $contact->id)
            ->where('channel', $channel->value)
            ->first()
            ?->status ?? ConsentStatus::None;
    }

    /**
     * Whether the contact may receive marketing messages on the channel.
     */
    public function hasMarketingConsent(Contact $contact, MessageChannel $channel): bool
    {
        return $this->status($contact, $channel) === ConsentStatus::Granted;
    }

    /**
     * Upsert the consent state and append an event, dispatching on change.
     */
    protected function record(Contact $contact, MessageChannel $channel, ConsentStatus $status, ConsentSource $source, ?Request $request): Consent
    {
        $previous = $this->status($contact, $channel);

        /** @var Consent $consent */
        $consent = $this->consentRepository->updateOrCreate(
            [
                'contact_id' => $contact->id,
                'channel'    => $channel->value,
            ],
            [
                'status'     => $status->value,
                'source'     => $source->value,
                'changed_at' => now(),
            ]
        );

        $event = ConsentEvent::query()->create([
            'contact_id' => $contact->id,
            'channel'    => $channel->value,
            'status'     => $status->value,
            'source'     => $source->value,
            'ip'         => $request?->ip(),
            'user_agent' => substr((string) $request?->userAgent(), 0, 255) ?: null,
            'meta'       => [],
            'created_at' => now(),
        ]);

        if ($previous !== $status) {
            Event::dispatch('marketing_automation.consent.changed', [$consent, $event]);
        }

        return $consent;
    }

    /**
     * Keep the core newsletter list in sync when email consent is revoked.
     */
    protected function unsubscribeFromNewsletter(Contact $contact): void
    {
        if ($contact->email === null) {
            return;
        }

        SubscribersList::query()
            ->where('email', $contact->email)
            ->where('channel_id', $contact->channel_id)
            ->update(['is_subscribed' => 0]);
    }
}
