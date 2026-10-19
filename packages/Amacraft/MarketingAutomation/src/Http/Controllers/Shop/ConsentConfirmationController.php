<?php

namespace Amacraft\MarketingAutomation\Http\Controllers\Shop;

use Amacraft\MarketingAutomation\Enums\ConsentStatus;
use Amacraft\MarketingAutomation\Enums\MessageChannel;
use Amacraft\MarketingAutomation\Repositories\ContactRepository;
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
        protected ContactRepository $contactRepository,
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
     * The page only says "confirmed" when the consent really ends up granted.
     */
    public function confirm(string $token): View
    {
        $parsed = $this->confirmation->parse($token);

        $contact = $parsed !== null
            ? $this->contactRepository->findOneByField('uuid', $parsed[0])
            : null;

        $consent = $contact !== null
            ? $this->consentService->confirmPending($contact, $parsed[1])
            : null;

        return view('marketing_automation::shop.consent-confirmation', [
            'token'     => $token,
            'channel'   => $parsed[1] ?? MessageChannel::Email,
            'confirmed' => $consent?->status === ConsentStatus::Granted,
        ]);
    }
}
