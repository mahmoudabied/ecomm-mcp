<?php

namespace Amacraft\MarketingAutomation\Listeners;

use Amacraft\MarketingAutomation\DTOs\TriggerEvent;
use Amacraft\MarketingAutomation\Enums\ConsentSource;
use Amacraft\MarketingAutomation\Enums\ConsentStatus;
use Amacraft\MarketingAutomation\Enums\MessageChannel;
use Amacraft\MarketingAutomation\Enums\TriggerType;
use Amacraft\MarketingAutomation\Jobs\IngestTrigger;
use Amacraft\MarketingAutomation\Repositories\ContactRepository;
use Amacraft\MarketingAutomation\Services\ConsentConfirmation;
use Amacraft\MarketingAutomation\Services\ConsentService;
use Amacraft\MarketingAutomation\Services\ContactCookie;
use Amacraft\MarketingAutomation\Services\ContactResolver;
use Webkul\Core\Models\Channel;
use Webkul\Customer\Contracts\Customer;

class CustomerListener
{
    /**
     * Create a new listener instance.
     */
    public function __construct(
        protected ContactRepository $contactRepository,
        protected ContactResolver $contactResolver,
        protected ContactCookie $contactCookie,
        protected ConsentService $consentService,
        protected ConsentConfirmation $confirmation,
    ) {}

    /**
     * Register the listeners for the automation module.
     */
    public function subscribe($events): void
    {
        $events->listen('customer.registration.after', [self::class, 'registered']);
        $events->listen('customer.after.login', [self::class, 'loggedIn']);
    }

    /**
     * A new customer registered: capture consents, then enrol into flows.
     */
    public function registered(Customer $customer): void
    {
        $channelId = $this->channelIdOf($customer);

        $this->grantRequestedConsents($customer, $channelId);

        IngestTrigger::dispatchDto(new TriggerEvent(
            type: TriggerType::CustomerRegistered,
            channelId: $channelId,
            customerId: $customer->id,
            email: $customer->email,
            phone: $customer->phone,
            triggerKey: "customer:{$customer->id}",
            payload: ['customer_id' => $customer->id],
            locale: $customer->locale ?? null,
        ));
    }

    /**
     * A customer logged in: fold their guest contact into theirs and identify the browser.
     */
    public function loggedIn(Customer $customer): void
    {
        $channelId = $this->channelIdOf($customer);

        $customerContact = $this->contactResolver->forCustomer($customer, $channelId);

        $guest = $this->contactRepository->findGuestByEmail($channelId, (string) $customer->email);

        if (
            $guest !== null
            && $guest->id !== $customerContact->id
        ) {
            $this->contactResolver->merge($guest, $customerContact);
        }

        $this->contactCookie->queue($customerContact);
    }

    /**
     * Persist the opt-in checkboxes of the signup form (US4). The signup
     * request itself is not a session, so with double opt-in on the grants
     * stay pending until the confirmation link is followed.
     */
    protected function grantRequestedConsents(Customer $customer, int $channelId): void
    {
        $consents = request()->input('marketing_consent', []);

        if (
            ! is_array($consents)
            || $consents === []
        ) {
            return;
        }

        $contact = $this->contactResolver->forCustomer($customer, $channelId);

        $salesChannel = Channel::query()->find($channelId) ?? core()->getDefaultChannel();

        foreach (MessageChannel::cases() as $channel) {
            if (! filter_var($consents[$channel->value] ?? null, FILTER_VALIDATE_BOOLEAN)) {
                continue;
            }

            $consent = $this->consentService->grant($contact, $channel, ConsentSource::Registration, request(), false);

            if ($consent->status === ConsentStatus::Pending) {
                $this->confirmation->request($contact, $channel, $salesChannel);
            }
        }
    }

    /**
     * The sales channel the customer belongs to, or the default one.
     */
    protected function channelIdOf(Customer $customer): int
    {
        return (int) ($customer->channel_id ?: core()->getDefaultChannel()?->id);
    }
}
