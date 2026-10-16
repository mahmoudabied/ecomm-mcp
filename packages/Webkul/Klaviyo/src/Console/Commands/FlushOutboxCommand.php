<?php

namespace Webkul\Klaviyo\Console\Commands;

use Illuminate\Console\Command;
use Webkul\Klaviyo\Models\Connection;
use Webkul\Klaviyo\Repositories\OutboxItemRepository;
use Webkul\Klaviyo\Services\FlushOutbox;

class FlushOutboxCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'klaviyo:flush-outbox {--connection= : Flush only this connection id}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Deliver the pending Klaviyo outbox rows, then purge delivered ones';

    /**
     * Days a delivered row is kept for troubleshooting.
     */
    protected const SENT_RETENTION_DAYS = 30;

    /**
     * Execute the console command.
     */
    public function handle(FlushOutbox $flusher, OutboxItemRepository $outboxItems): int
    {
        $connections = Connection::query()
            ->when($this->option('connection'), fn ($query) => $query->where('id', (int) $this->option('connection')))
            ->get();

        foreach ($connections as $connection) {
            $flusher->flush((int) $connection->id);
        }

        $purged = $outboxItems->purgeSent(self::SENT_RETENTION_DAYS);

        $this->info("Flushed {$connections->count()} connection(s), purged {$purged} delivered rows.");

        return self::SUCCESS;
    }
}
