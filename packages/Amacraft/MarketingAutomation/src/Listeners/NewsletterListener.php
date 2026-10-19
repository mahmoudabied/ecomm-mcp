<?php

namespace Amacraft\MarketingAutomation\Listeners;

use Webkul\Core\Models\SubscribersList;
use Amacraft\MarketingAutomation\DTOs\TriggerEvent;
use Amacraft\MarketingAutomation\Enums\ConsentSource;
use Amacraft\MarketingAutomation\Enums\MessageChannel;
use Amacraft\MarketingAutomation\Enums\TriggerType;
use Amacraft\MarketingAutomation\Jobs\IngestTrigger;
use Amacraft\MarketingAutomation\Services\ConsentService;
use Amacraft\MarketingAutomation\Services\ContactResolver;

class NewsletterListener
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
        $events->listen('customer.subscription.after', [self::class, 'subscribed']);
    }

    /**
     * A newsletter subscription: grant email consent, then enrol into flows.
     */
    public function subscribed(SubscribersList $subscriber): void
    {
        $channelId = (int) ($subscriber->channel_id ?: core()->getDefaultChannel()?->id);

        if ($subscriber->is_subscribed) {
            $contact = $this->contactResolver->resolve(
                channelId: $channelId,
                customerId: $subscriber->customer_id,
                email: $subscriber->subscriber_email,
            );

            $this->consentService->grant($contact, MessageChannel::Email, ConsentSource::Newsletter);
        }

        IngestTrigger::dispatchDto(new TriggerEvent(
            type: TriggerType::NewsletterSubscribed,
            channelId: $channelId,
            customerId: $subscriber->customer_id,
            email: $subscriber->subscriber_email,
            phone: null,
            triggerKey: "subscribe:{$subscriber->id}",
            payload: ['subscriber_id' => $subscriber->id],
        ));
    }
}
