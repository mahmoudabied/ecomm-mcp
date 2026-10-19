<?php

namespace Amacraft\MarketingAutomation\Http\Controllers\Shop;

use Amacraft\MarketingAutomation\Enums\MessageChannel;
use Amacraft\MarketingAutomation\Models\Contact;
use Amacraft\MarketingAutomation\Services\ConsentConfirmation;
use Amacraft\MarketingAutomation\Services\ConsentService;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

class ConsentConfirmationController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct(
        protected ConsentConfirmation $confirmation,
        protected ConsentService $consentService,
    ) {}

    /**
     * Show the confirmation page for a double opt-in request.
     */
    public function show(string $token): View
    {
        return view('marketing_automation::shop.consent-confirmation', [
            'token'     => $token,
            'channel'   => $this->confirmation->parse($token)[1] ?? MessageChannel::Email,
            'confirmed' => false,
        ]);
    }

    /**
     * Grant the pending consent; the signed token is the proof of ownership.
     */
    public function confirm(string $token): View
    {
        $parsed = $this->confirmation->parse($token);

        if ($parsed !== null) {
            /** @var ?Contact $contact */
            $contact = Contact::query()->where('uuid', $parsed[0])->first();

            if ($contact !== null) {
                $this->consentService->confirmPending($contact, $parsed[1]);
            }
        }

        return view('marketing_automation::shop.consent-confirmation', [
            'token'     => $token,
            'channel'   => $parsed[1] ?? MessageChannel::Email,
            'confirmed' => $parsed !== null,
        ]);
    }
}
