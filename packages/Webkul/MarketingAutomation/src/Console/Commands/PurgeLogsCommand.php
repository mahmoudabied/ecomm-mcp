<?php

namespace Webkul\MarketingAutomation\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Webkul\MarketingAutomation\Services\Settings;

class PurgeLogsCommand extends Command
{
    /**
     * The number of rows deleted per batch.
     */
    protected const BATCH_SIZE = 1000;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'automation:purge-logs';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete message and enrolment data older than the retention period (FR-032a)';

    /**
     * Create a new command instance.
     */
    public function __construct(
        protected Settings $settings,
    ) {
        parent::__construct();
    }

    /**
     * Purge aged personal data in batches. Rollups and attributions are kept,
     * so historical reports stay intact while raw messages are dropped.
     */
    public function handle(): int
    {
        $cutoff = now()->subMonths($this->settings->retentionMonths());

        $this->deleteInBatches('automation_message_events', 'created_at', $cutoff);

        $this->deleteInBatches('automation_message_logs', 'created_at', $cutoff);

        $this->deleteInBatches('automation_enrolment_events', 'created_at', $cutoff);

        $this->deleteFinishedEnrolments($cutoff);

        return Command::SUCCESS;
    }

    /**
     * Delete rows older than the cutoff, at most one batch at a time so long
     * locks never pile up.
     */
    protected function deleteInBatches(string $table, string $column, Carbon $cutoff): void
    {
        do {
            $deleted = DB::table($table)
                ->where($column, '<', $cutoff)
                ->limit(self::BATCH_SIZE)
                ->delete();
        } while ($deleted > 0);
    }

    /**
     * Remove enrolments that finished before the cutoff; active journeys
     * stay. Rows cancelled without a completion timestamp (legacy flows
     * deleted before it was stamped) fall back to their last touch date.
     */
    protected function deleteFinishedEnrolments(Carbon $cutoff): void
    {
        do {
            $deleted = DB::table('automation_enrolments')
                ->where('status', '!=', 'active')
                ->where(function ($query) use ($cutoff) {
                    $query
                        ->where('completed_at', '<', $cutoff)
                        ->orWhere(fn ($fallback) => $fallback
                            ->whereNull('completed_at')
                            ->where('updated_at', '<', $cutoff));
                })
                ->limit(self::BATCH_SIZE)
                ->delete();
        } while ($deleted > 0);
    }
}
