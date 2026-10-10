<?php

namespace Webkul\MarketingAutomation\Services;

use Webkul\Core\Models\Channel;
use Webkul\MarketingAutomation\Enums\FlowType;
use Webkul\MarketingAutomation\Enums\MessageChannel;
use Webkul\MarketingAutomation\Enums\SkipReason;
use Webkul\MarketingAutomation\Models\Contact;

class SendPolicy
{
    /**
     * Create a new send policy instance.
     */
    public function __construct(
        protected ConsentService $consentService,
        protected ProviderManager $providerManager,
        protected Settings $settings,
    ) {}

    /**
     * Decide whether a message may be sent to the contact right now.
     */
    public function check(Contact $contact, MessageChannel $channel, FlowType $type, Channel $salesChannel): PolicyResult
    {
        $recipient = $this->recipientFor($contact, $channel);

        if ($recipient === null) {
            return PolicyResult::skip(SkipReason::InvalidContact);
        }

        if ($type === FlowType::Marketing && ! $this->consentService->hasMarketingConsent($contact, $channel)) {
            return PolicyResult::skip(SkipReason::NoConsent);
        }

        if ($channel !== MessageChannel::Email) {
            $connection = $this->providerManager->defaultFor($salesChannel->id, $channel);

            if ($connection === null || $connection->status->value !== 'connected') {
                return PolicyResult::skip(SkipReason::NoProvider);
            }
        }

        if ($timing = $this->applyTimingRules($contact, $channel, $type, $salesChannel)) {
            return $timing;
        }

        return PolicyResult::allow($recipient);
    }

    /**
     * Resolve the address the message would go to.
     */
    protected function recipientFor(Contact $contact, MessageChannel $channel): ?string
    {
        if ($channel === MessageChannel::Email) {
            $email = (string) $contact->email;

            return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
        }

        return $contact->phone_e164;
    }

    /**
     * Quiet hours and frequency cap for marketing messages (US4 completes this).
     */
    protected function applyTimingRules(Contact $contact, MessageChannel $channel, FlowType $type, Channel $salesChannel): ?PolicyResult
    {
        // US4: quiet hours & cap — see T103.
        return null;
    }
}
