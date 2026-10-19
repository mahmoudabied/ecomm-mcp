<?php

namespace Amacraft\MarketingAutomation\Http\Controllers\Shop;

use Amacraft\MarketingAutomation\Contracts\Contact;
use Amacraft\MarketingAutomation\Enums\ConsentSource;
use Amacraft\MarketingAutomation\Enums\ConsentStatus;
use Amacraft\MarketingAutomation\Enums\MessageChannel;
use Amacraft\MarketingAutomation\Http\Requests\CheckoutContactRequest;
use Amacraft\MarketingAutomation\Rendering\ChannelUrl;
use Amacraft\MarketingAutomation\Services\ConsentConfirmation;
use Amacraft\MarketingAutomation\Services\ConsentService;
use Amacraft\MarketingAutomation\Services\ContactCookie;
use Amacraft\MarketingAutomation\Services\ContactResolver;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Webkul\Checkout\Cart as CartHelper;
use Webkul\Checkout\Repositories\CartRepository;
use Webkul\Core\Models\Channel;

class CheckoutContactController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct(
        protected ContactResolver $contactResolver,
        protected ContactCookie $contactCookie,
        protected ConsentService $consentService,
        protected ConsentConfirmation $confirmation,
        protected ChannelUrl $channelUrl,
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

        $trusted = $customer !== null;

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
            trusted: $trusted,
        );

        $this->grantRequestedConsents($request, $contact, $trusted, $this->salesChannel($channelId));

        if ($trusted || $contact->wasRecentlyCreated) {
            $this->contactCookie->queue($contact);
        }

        return response('', 204);
    }

    /**
     * Grant every checkbox the shopper ticked, with the checkout source.
     * Requests without a session only start pending consents, so a third
     * party cannot sign somebody else's address up for marketing.
     */
    protected function grantRequestedConsents(
        CheckoutContactRequest $request,
        Contact $contact,
        bool $trusted,
        Channel $salesChannel,
    ): void {
        $consents = (array) $request->input('consents', []);

        foreach (MessageChannel::cases() as $channel) {
            if (! filter_var($consents[$channel->value] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                continue;
            }

            $consent = $this->consentService->grant($contact, $channel, ConsentSource::Checkout, $request, $trusted);

            if ($consent->status === ConsentStatus::Pending) {
                $this->confirmation->request($contact, $channel, $salesChannel);
            }
        }
    }

    /**
     * The sales channel the messages are built for.
     */
    protected function salesChannel(int $channelId): Channel
    {
        return $this->channelUrl->channel($channelId);
    }
}
