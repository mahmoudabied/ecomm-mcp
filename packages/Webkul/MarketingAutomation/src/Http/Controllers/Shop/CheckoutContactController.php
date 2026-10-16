<?php

namespace Webkul\MarketingAutomation\Http\Controllers\Shop;

use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Webkul\Checkout\Cart as CartHelper;
use Webkul\Checkout\Repositories\CartRepository;
use Webkul\MarketingAutomation\Contracts\Contact;
use Webkul\MarketingAutomation\Enums\ConsentSource;
use Webkul\MarketingAutomation\Enums\MessageChannel;
use Webkul\MarketingAutomation\Http\Requests\CheckoutContactRequest;
use Webkul\MarketingAutomation\Services\ConsentService;
use Webkul\MarketingAutomation\Services\ContactCookie;
use Webkul\MarketingAutomation\Services\ContactResolver;

class CheckoutContactController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct(
        protected ContactResolver $contactResolver,
        protected ContactCookie $contactCookie,
        protected ConsentService $consentService,
        protected CartHelper $cartHelper,
        protected CartRepository $cartRepository,
    ) {}

    /**
     * Capture the guest's email and phone at checkout, grant the ticked consents and set the visitor cookie.
     */
    public function store(CheckoutContactRequest $request): Response
    {
        $cart = $this->cartHelper->getCart();

        $channelId = (int) ($cart?->channel_id ?: core()->getCurrentChannel()->id);

        $customer = auth()->guard('customer')->user();

        if (
            $cart !== null
            && empty($cart->customer_email)
            && filled($request->input('email'))
        ) {
            $this->cartRepository->update(['customer_email' => $request->input('email')], $cart->id);
        }

        $contact = $this->contactResolver->resolve(
            channelId: $channelId,
            customerId: $customer?->id,
            email: $request->input('email'),
            phone: $request->input('phone'),
            country: $cart?->billing_address?->country,
        );

        $this->grantRequestedConsents($request, $contact);

        $this->contactCookie->queue($contact);

        return response('', 204);
    }

    /**
     * Grant every checkbox the shopper ticked, with the checkout source.
     */
    protected function grantRequestedConsents(CheckoutContactRequest $request, Contact $contact): void
    {
        $consents = (array) $request->input('consents', []);

        foreach (MessageChannel::cases() as $channel) {
            if (filter_var($consents[$channel->value] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                $this->consentService->grant($contact, $channel, ConsentSource::Checkout, $request);
            }
        }
    }
}
