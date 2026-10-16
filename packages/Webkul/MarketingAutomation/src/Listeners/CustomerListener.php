<?php

namespace Webkul\MarketingAutomation\Listeners;

use Webkul\Customer\Contracts\Customer;
use Webkul\MarketingAutomation\DTOs\TriggerEvent;
use Webkul\MarketingAutomation\Enums\ConsentSource;
use Webkul\MarketingAutomation\Enums\MessageChannel;
use Webkul\MarketingAutomation\Enums\TriggerType;
use Webkul\MarketingAutomation\Jobs\IngestTrigger;
use Webkul\MarketingAutomation\Repositories\ContactRepository;
use Webkul\MarketingAutomation\Services\ConsentService;
use Webkul\MarketingAutomation\Services\ContactCookie;
use Webkul\MarketingAutomation\Services\ContactResolver;

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
     * Persist the opt-in checkboxes of the signup form (US4).
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

        foreach (MessageChannel::cases() as $channel) {
            if (filter_var($consents[$channel->value] ?? null, FILTER_VALIDATE_BOOLEAN)) {
                $this->consentService->grant($contact, $channel, ConsentSource::Registration, request());
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
