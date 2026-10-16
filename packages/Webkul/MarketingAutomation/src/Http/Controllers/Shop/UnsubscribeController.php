<?php

namespace Webkul\MarketingAutomation\Http\Controllers\Shop;

use Illuminate\Http\Response;
use Illuminate\View\View;
use Webkul\MarketingAutomation\Enums\ConsentSource;
use Webkul\MarketingAutomation\Enums\MessageChannel;
use Webkul\MarketingAutomation\Enums\MessageEventType;
use Webkul\MarketingAutomation\Models\Contact;
use Webkul\MarketingAutomation\Models\MessageEvent;
use Webkul\MarketingAutomation\Models\MessageLog;
use Webkul\MarketingAutomation\Services\ConsentService;
use Webkul\MarketingAutomation\Services\SignedToken;

class UnsubscribeController
{
    /**
     * Create a new controller instance.
     */
    public function __construct(
        protected SignedToken $signedToken,
        protected ConsentService $consentService,
    ) {}

    /**
     * Show the unsubscribe confirmation page.
     */
    public function show(string $token): View
    {
        [, $channel] = $this->parse($token);

        return view('marketing_automation::shop.unsubscribe', [
            'token'     => $token,
            'channel'   => $channel,
            'confirmed' => false,
        ]);
    }

    /**
     * Revoke the consent immediately; a one-click (RFC 8058) POST gets an empty 200.
     */
    public function confirm(string $token): View|Response
    {
        [$contactUuid, $channel, $messageUuid] = $this->parse($token);

        /** @var ?Contact $contact */
        $contact = Contact::query()->where('uuid', $contactUuid)->first();

        if ($contact !== null) {
            $this->consentService->revoke($contact, $channel, ConsentSource::UnsubscribeLink, request());

            $this->recordUnsubscribed($messageUuid);
        }

        if (request()->input('List-Unsubscribe') === 'One-Click') {
            return response('', 200);
        }

        return view('marketing_automation::shop.unsubscribe', [
            'token'     => $token,
            'channel'   => $channel,
            'confirmed' => true,
        ]);
    }

    /**
     * Verify the token, aborting with 404 when it was tampered with.
     *
     * @return array{0: string, 1: MessageChannel, 2: string}
     */
    protected function parse(string $token): array
    {
        $parts = $this->signedToken->parse($token);

        $channel = MessageChannel::tryFrom((string) ($parts[1] ?? ''));

        abort_if($parts === null || count($parts) !== 3 || $channel === null, 404);

        return [$parts[0], $channel, $parts[2]];
    }

    /**
     * Note the unsubscribe on the message that carried the link.
     */
    protected function recordUnsubscribed(string $messageUuid): void
    {
        $messageLogId = MessageLog::query()->where('uuid', $messageUuid)->value('id');

        if ($messageLogId === null) {
            return;
        }

        MessageEvent::query()->create([
            'message_log_id' => $messageLogId,
            'type'           => MessageEventType::Unsubscribed->value,
            'occurred_at'    => now(),
        ]);
    }
}
