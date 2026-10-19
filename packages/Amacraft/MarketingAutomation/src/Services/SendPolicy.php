<?php

namespace Amacraft\MarketingAutomation\Services;

use Carbon\Carbon;
use Webkul\Core\Models\Channel;
use Amacraft\MarketingAutomation\Enums\FlowType;
use Amacraft\MarketingAutomation\Enums\MessageChannel;
use Amacraft\MarketingAutomation\Enums\SkipReason;
use Amacraft\MarketingAutomation\Models\Contact;
use Amacraft\MarketingAutomation\Repositories\MessageLogRepository;

class SendPolicy
{
    /**
     * Create a new send policy instance.
     */
    public function __construct(
        protected ConsentService $consentService,
        protected ProviderManager $providerManager,
        protected Settings $settings,
        protected MessageLogRepository $messageLogRepository,
    ) {}

    /**
     * Decide whether a message may be sent to the contact right now.
     */
    public function check(
        Contact $contact,
        MessageChannel $channel,
        FlowType $type,
        Channel $salesChannel,
        ?int $enrolmentId = null,
    ): PolicyResult {
        $recipient = $this->recipientFor($contact, $channel);

        if ($recipient === null) {
            return PolicyResult::skip(SkipReason::InvalidContact);
        }

        if (
            $type === FlowType::Marketing
            && ! $this->consentService->hasMarketingConsent($contact, $channel)
        ) {
            return PolicyResult::skip(SkipReason::NoConsent);
        }

        if ($channel !== MessageChannel::Email) {
            $connection = $this->providerManager->defaultFor($salesChannel->id, $channel);

            if (
                $connection === null
                || $connection->status->value !== 'connected'
            ) {
                return PolicyResult::skip(SkipReason::NoProvider);
            }
        }

        if ($timing = $this->applyTimingRules($contact, $channel, $type, $salesChannel, $enrolmentId)) {
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
     * Quiet hours and frequency cap for marketing messages; transactional ones are never held back.
     */
    protected function applyTimingRules(
        Contact $contact,
        MessageChannel $channel,
        FlowType $type,
        Channel $salesChannel,
        ?int $enrolmentId,
    ): ?PolicyResult {
        if ($type !== FlowType::Marketing) {
            return null;
        }

        $deferUntil = null;

        $reason = null;

        if ($quiet = $this->quietHoursDeferUntil($contact, $channel, $salesChannel)) {
            $deferUntil = $quiet;

            $reason = SkipReason::QuietHours;
        }

        if ($cap = $this->capDeferUntil($contact, $channel, $salesChannel, $enrolmentId)) {
            if (
                $deferUntil === null
                || $cap->greaterThan($deferUntil)
            ) {
                $deferUntil = $cap;

                $reason = SkipReason::FrequencyCap;
            }
        }

        if ($deferUntil === null) {
            return null;
        }

        return PolicyResult::defer($reason ?? SkipReason::QuietHours, $deferUntil);
    }

    /**
     * When quiet hours end, if an SMS or WhatsApp send would land inside them; an overnight window ends tomorrow.
     */
    protected function quietHoursDeferUntil(Contact $contact, MessageChannel $channel, Channel $salesChannel): ?Carbon
    {
        if ($channel === MessageChannel::Email) {
            return null;
        }

        $quiet = $this->settings->quietHours($salesChannel->code);

        if ($quiet === null) {
            return null;
        }

        $timezone = $this->settings->timezone($salesChannel);

        $now = now()->copy()->setTimezone($timezone);

        $start = Carbon::createFromFormat('H:i', $quiet['start'], $timezone)->setDateFrom($now);

        $end = Carbon::createFromFormat('H:i', $quiet['end'], $timezone)->setDateFrom($now);

        if ($start->equalTo($end)) {
            return null;
        }

        $within = $start->lessThan($end)
            ? $now->greaterThanOrEqualTo($start) && $now->lessThan($end)
            : $now->greaterThanOrEqualTo($start) || $now->lessThan($end);

        if (! $within) {
            return null;
        }

        if ($now->greaterThanOrEqualTo($end)) {
            $end->addDay();
        }

        return $end->copy()->setTimezone(config('app.timezone'));
    }

    /**
     * When the oldest message in the cap window ages out, if the contact already reached the cap.
     */
    protected function capDeferUntil(Contact $contact, MessageChannel $channel, Channel $salesChannel, ?int $enrolmentId): ?Carbon
    {
        $cap = $this->settings->cap($salesChannel->code);

        if ($cap['count'] < 1) {
            return null;
        }

        $recent = $this->messageLogRepository->marketingSentSince(
            $contact->id,
            $channel->value,
            now()->subHours($cap['window_hours']),
            $enrolmentId,
        );

        if ($recent->count() < $cap['count']) {
            return null;
        }

        $oldest = $recent->first();

        return $oldest->created_at
            ->copy()
            ->addHours($cap['window_hours'])
            ->addMinute()
            ->setTimezone(config('app.timezone'));
    }
}
