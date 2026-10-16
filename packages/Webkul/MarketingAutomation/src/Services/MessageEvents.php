<?php

namespace Webkul\MarketingAutomation\Services;

use Webkul\MarketingAutomation\Enums\MessageEventType;
use Webkul\MarketingAutomation\Jobs\RecordMessageEvent;

class MessageEvents
{
    /**
     * Queue the recording of one delivery or engagement event on a message.
     *
     * @param  array<string, mixed>  $meta
     */
    public function record(string $messageUuid, MessageEventType $type, array $meta = []): void
    {
        RecordMessageEvent::dispatch($messageUuid, $type, $meta);
    }
}
