<?php

namespace Amacraft\MarketingAutomation\Jobs;

use Amacraft\MarketingAutomation\Enums\MessageChannel;
use Amacraft\MarketingAutomation\Repositories\ContactRepository;
use Amacraft\MarketingAutomation\Services\ConsentConfirmation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Webkul\Core\Repositories\ChannelRepository;

class SendConsentConfirmation implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $contactId,
        public string $channel,
        public int $salesChannelId,
    ) {
        $this->onQueue(config('marketing-automation.queues.automation'));
    }

    /**
     * Deliver the double opt-in link off the request, so a mailer or provider outage never fails checkout.
     */
    public function handle(
        ContactRepository $contactRepository,
        ChannelRepository $channelRepository,
        ConsentConfirmation $confirmation,
    ): void {
        $contact = $contactRepository->find($this->contactId);

        $salesChannel = $channelRepository->find($this->salesChannelId);

        $channel = MessageChannel::tryFrom($this->channel);

        if (
            $contact === null
            || $salesChannel === null
            || $channel === null
        ) {
            return;
        }

        $confirmation->send($contact, $channel, $salesChannel);
    }
}
