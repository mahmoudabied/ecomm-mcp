<?php

namespace Amacraft\MarketingAutomation\Services;

use Amacraft\MarketingAutomation\DTOs\OutboundMessage;
use Amacraft\MarketingAutomation\DTOs\WhatsAppTemplateCall;
use Amacraft\MarketingAutomation\Enums\MessageChannel;
use Amacraft\MarketingAutomation\Enums\WhatsAppTemplateStatus;
use Amacraft\MarketingAutomation\Jobs\SendConsentConfirmation;
use Amacraft\MarketingAutomation\Mail\AutomationMail;
use Amacraft\MarketingAutomation\Models\Contact;
use Amacraft\MarketingAutomation\Models\ProviderConnection;
use Amacraft\MarketingAutomation\Rendering\ChannelUrl;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Webkul\Core\Models\Channel;

class ConsentConfirmation
{
    /**
     * How long a confirmation link stays valid, in days.
     */
    protected const TTL_DAYS = 3;

    /**
     * The number of confirmations one email address or phone may receive per day.
     */
    protected const RECIPIENT_LIMIT_PER_DAY = 3;

    /**
     * Create a new consent confirmation instance.
     */
    public function __construct(
        protected SignedToken $signedToken,
        protected ChannelUrl $channelUrl,
        protected Settings $settings,
        protected ProviderManager $providerManager,
    ) {}

    /**
     * Queue the confirmation for a pending consent, unless the recipient or the store hit the daily cap.
     */
    public function request(Contact $contact, MessageChannel $channel, Channel $salesChannel): void
    {
        $recipient = $channel === MessageChannel::Email ? $contact->email : $contact->phone_e164;

        if (
            $recipient === null
            || ! $this->withinLimits($channel, $recipient)
        ) {
            return;
        }

        SendConsentConfirmation::dispatch((int) $contact->id, $channel->value, (int) $salesChannel->id);
    }

    /**
     * Deliver the double opt-in confirmation for a pending consent request.
     */
    public function send(Contact $contact, MessageChannel $channel, Channel $salesChannel): void
    {
        $link = $this->link($contact, $channel, $salesChannel);

        if ($channel === MessageChannel::Email) {
            $this->sendEmail($contact, $channel, $salesChannel, $link);

            return;
        }

        $this->sendViaProvider($contact, $channel, $salesChannel, $link);
    }

    /**
     * The signed, expiring confirmation link of a contact and channel.
     */
    public function link(Contact $contact, MessageChannel $channel, Channel $salesChannel): string
    {
        return $this->channelUrl->to('shop.automation.consent.confirm', $salesChannel, $this->token($contact, $channel));
    }

    /**
     * The signed token of a contact and channel.
     */
    public function token(Contact $contact, MessageChannel $channel): string
    {
        return $this->signedToken->make(
            'consent',
            (string) $contact->uuid,
            $channel->value,
            (string) now()->addDays(self::TTL_DAYS)->getTimestamp(),
        );
    }

    /**
     * The contact uuid and channel of a token, or null when it was tampered with, is of another kind or expired.
     *
     * @return array{0: string, 1: MessageChannel}|null
     */
    public function parse(string $token): ?array
    {
        $parts = $this->signedToken->parse($token);

        $channel = MessageChannel::tryFrom((string) ($parts[2] ?? ''));

        if (
            $parts === null
            || count($parts) !== 4
            || $parts[0] !== 'consent'
            || $channel === null
            || (int) $parts[3] < now()->getTimestamp()
        ) {
            return null;
        }

        return [$parts[1], $channel];
    }

    /**
     * Count one confirmation against the store-wide and per-recipient daily caps; true when it may go out.
     */
    protected function withinLimits(MessageChannel $channel, string $recipient): bool
    {
        $dailyLimit = (int) config('marketing-automation.confirmation.daily_limit', 500);

        if (! RateLimiter::attempt('ma-confirm:'.$channel->value, $dailyLimit, fn () => null, 86400)) {
            return false;
        }

        return RateLimiter::attempt(
            'ma-confirm:'.$channel->value.':'.sha1(strtolower($recipient)),
            self::RECIPIENT_LIMIT_PER_DAY,
            fn () => null,
            86400,
        );
    }

    /**
     * Mail the confirmation link to the contact.
     */
    protected function sendEmail(Contact $contact, MessageChannel $channel, Channel $salesChannel, string $link): void
    {
        if ($contact->email === null) {
            return;
        }

        Mail::to($contact->email)->send(new AutomationMail(
            subjectText: trans('marketing_automation::app.consent.confirmation.subject'),
            bodyHtml: view('marketing_automation::emails.consent-confirmation', [
                'link'    => $link,
                'channel' => $channel,
            ])->render(),
            fromAddress: $this->settings->senderAddress($salesChannel->code),
            fromName: $this->settings->senderName($salesChannel->code),
        ));
    }

    /**
     * Send the confirmation link through the channel's default provider; WhatsApp
     * needs an approved utility template, since free text is rejected outside a conversation.
     */
    protected function sendViaProvider(
        Contact $contact,
        MessageChannel $channel,
        Channel $salesChannel,
        string $link,
    ): void {
        $connection = $this->providerManager->defaultFor($salesChannel->id, $channel);

        if ($connection === null || $contact->phone_e164 === null) {
            return;
        }

        $isWhatsApp = $channel === MessageChannel::Whatsapp;

        $template = $isWhatsApp ? $this->whatsappTemplateCall($connection, $link) : null;

        if ($isWhatsApp && $template === null) {
            return;
        }

        $this->providerManager->driver($connection)->send($connection, new OutboundMessage(
            to: $contact->phone_e164,
            channel: $channel->value,
            body: $isWhatsApp ? null : trans('marketing_automation::app.consent.confirmation.sms', ['link' => $link]),
            template: $template,
            messageUuid: (string) Str::uuid(),
        ));
    }

    /**
     * The approved utility template carrying the confirmation link.
     */
    protected function whatsappTemplateCall(ProviderConnection $connection, string $link): ?WhatsAppTemplateCall
    {
        $name = (string) config('marketing-automation.confirmation.whatsapp_template', 'consent_confirm');

        $template = $connection->templates()
            ->where('name', $name)
            ->where('status', WhatsAppTemplateStatus::Approved->value)
            ->first();

        if ($template === null) {
            return null;
        }

        return new WhatsAppTemplateCall(name: $template->name, language: $template->language, bodyParams: [$link]);
    }
}
