<?php

namespace Webkul\MarketingAutomation\Listeners;

use Webkul\Customer\Models\Customer;
use Webkul\MarketingAutomation\DTOs\TriggerEvent;
use Webkul\MarketingAutomation\Enums\ConsentSource;
use Webkul\MarketingAutomation\Enums\MessageChannel;
use Webkul\MarketingAutomation\Enums\TriggerType;
use Webkul\MarketingAutomation\Jobs\IngestTrigger;
use Webkul\MarketingAutomation\Services\ConsentService;
use Webkul\MarketingAutomation\Services\ContactResolver;

class CustomerListener
{
    /**
     * Create a new listener instance.
     */
    public function __construct(
        protected ContactResolver $contactResolver,
        protected ConsentService $consentService,
    ) {}

    /**
     * Register the listeners for the automation module.
     */
    public function subscribe($events): void
    {
        $events->listen('customer.registration.after', [self::class, 'registered']);
    }

    /**
     * A new customer registered: capture consents, then enrol into flows.
     */
    public function registered(Customer $customer): void
    {
        $channelId = (int) ($customer->channel_id ?: core()->getDefaultChannel()?->id);

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
     * Persist the opt-in checkboxes of the signup form (US4).
     */
    protected function grantRequestedConsents(Customer $customer, int $channelId): void
    {
        $consents = request()->input('marketing_consent', []);

        if (! is_array($consents) || $consents === []) {
            return;
        }

        $contact = $this->contactResolver->forCustomer($customer, $channelId);

        foreach (MessageChannel::cases() as $channel) {
            if (filter_var($consents[$channel->value] ?? null, FILTER_VALIDATE_BOOLEAN)) {
                $this->consentService->grant($contact, $channel, ConsentSource::Registration, request());
            }
        }
    }
}
