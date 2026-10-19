<?php

namespace Amacraft\MarketingAutomation\Http\Controllers\Shop;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Amacraft\MarketingAutomation\Contracts\Contact;
use Amacraft\MarketingAutomation\Enums\ConsentSource;
use Amacraft\MarketingAutomation\Enums\ConsentStatus;
use Amacraft\MarketingAutomation\Enums\MessageChannel;
use Amacraft\MarketingAutomation\Services\ConsentService;
use Amacraft\MarketingAutomation\Services\ContactResolver;
use Amacraft\MarketingAutomation\Services\GdprService;

class MarketingPreferencesController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct(
        protected ContactResolver $contactResolver,
        protected ConsentService $consentService,
        protected GdprService $gdprService,
    ) {}

    /**
     * Show the per-channel marketing preferences of the customer.
     */
    public function index(): View
    {
        $contact = $this->contactForCustomer();

        $consents = [];

        foreach (MessageChannel::cases() as $channel) {
            $consents[$channel->value] = $contact !== null
                ? $this->consentService->status($contact, $channel) === ConsentStatus::Granted
                : false;
        }

        return view('marketing_automation::shop.account.marketing-preferences', [
            'consents' => $consents,
        ]);
    }

    /**
     * Save the per-channel marketing preferences with the account source.
     */
    public function update(Request $request): RedirectResponse
    {
        $contact = $this->contactForCustomer();

        if ($contact !== null) {
            foreach (MessageChannel::cases() as $channel) {
                $granted = $request->boolean("consents.{$channel->value}");

                $current = $this->consentService->status($contact, $channel);

                if (
                    $granted
                    && $current !== ConsentStatus::Granted
                ) {
                    $this->consentService->grant($contact, $channel, ConsentSource::Account, $request);
                }

                if (
                    ! $granted
                    && $current === ConsentStatus::Granted
                ) {
                    $this->consentService->revoke($contact, $channel, ConsentSource::Account, $request);
                }
            }
        }

        session()->flash('success', trans('marketing_automation::app.shop.preferences.saved'));

        return redirect()->route('shop.customers.account.marketing-preferences.index');
    }

    /**
     * Download the customer's marketing data as a JSON file (FR-025).
     */
    public function export(): JsonResponse
    {
        return response()
            ->json($this->gdprService->export(auth()->guard('customer')->user()))
            ->header('Content-Disposition', 'attachment; filename=marketing-data.json');
    }

    /**
     * The automation contact of the logged-in customer, creating it when
     * the preferences are first opened or saved.
     */
    protected function contactForCustomer(): ?Contact
    {
        $customer = auth()->guard('customer')->user();

        if ($customer === null) {
            return null;
        }

        $channelId = (int) ($customer->channel_id ?: core()->getDefaultChannel()?->id);

        return $this->contactResolver->forCustomer($customer, $channelId);
    }
}
