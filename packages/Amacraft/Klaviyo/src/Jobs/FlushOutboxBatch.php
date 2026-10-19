<?php

namespace Amacraft\Klaviyo\Jobs;

use Amacraft\Klaviyo\Services\FlushOutbox;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

class FlushOutboxBatch implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $connectionId,
    ) {
        $this->onQueue(config('marketing-automation.queues.klaviyo'));
    }

    /**
     * Flush the due rows of one connection.
     */
    public function handle(FlushOutbox $flusher): void
    {
        $flusher->flush($this->connectionId);
    }
}
