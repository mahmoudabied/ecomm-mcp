<?php

namespace Amacraft\Klaviyo\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Spatie\Multitenancy\Jobs\NotTenantAware;
use Amacraft\Klaviyo\Services\FlushOutbox;

class FlushOutboxBatch implements NotTenantAware, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $connectionId,
    ) {
        $this->onQueue('klaviyo');
    }

    /**
     * Flush the due rows of one connection.
     */
    public function handle(FlushOutbox $flusher): void
    {
        $flusher->flush($this->connectionId);
    }
}
