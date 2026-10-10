<?php

namespace Webkul\MarketingAutomation\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;
use Webkul\MarketingAutomation\Engine\StepRunner;
use Webkul\MarketingAutomation\Enums\EnrolmentStatus;
use Webkul\MarketingAutomation\Enums\FlowStatus;
use Webkul\MarketingAutomation\Jobs\ProcessEnrolmentStep;
use Webkul\MarketingAutomation\Services\HealthCheck;

class DispatchDueCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'automation:dispatch-due {--now : Dispatch synchronously instead of queueing}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Claim and dispatch the enrolment steps that are due';

    /**
     * How many enrolments one loop claims at most.
     */
    protected const CHUNK = 500;

    /**
     * The most loops per run, guarding against pathological backlogs.
     */
    protected const MAX_LOOPS = 20;

    /**
     * Execute the console command.
     */
    public function handle(HealthCheck $healthCheck): int
    {
        $healthCheck->beat();

        $dispatched = 0;

        for ($loop = 0; $loop < self::MAX_LOOPS; $loop++) {
            $claimed = $this->claimChunk();

            if ($claimed === []) {
                break;
            }

            foreach ($claimed as $row) {
                if ($this->option('now')) {
                    $this->runNow((int) $row->id, (string) $row->lock_token);
                } else {
                    ProcessEnrolmentStep::dispatch((int) $row->id, (string) $row->lock_token);
                }

                $dispatched++;
            }

            if (count($claimed) < self::CHUNK) {
                break;
            }
        }

        $this->info("Dispatched {$dispatched} due enrolment steps.");

        return self::SUCCESS;
    }

    /**
     * Run one step in-process. There is no queue to retry it, so a throwing
     * step is closed out through failed() like an exhausted job, and the
     * remaining rows still run.
     */
    protected function runNow(int $enrolmentId, string $lockToken): void
    {
        $job = new ProcessEnrolmentStep($enrolmentId, $lockToken);

        try {
            $job->handle(app(StepRunner::class));
        } catch (Throwable $exception) {
            report($exception);

            $job->failed($exception);
        }
    }

    /**
     * Claim the next chunk of due enrolments with a unique token.
     *
     * @return array<int, object>
     */
    protected function claimChunk(): array
    {
        return DB::transaction(function () {
            $token = (string) Str::ulid();

            $ids = DB::table('automation_enrolments')
                ->where('status', EnrolmentStatus::Active->value)
                ->where('next_run_at', '<=', now())
                ->where(fn ($query) => $query->whereNull('locked_until')->orWhere('locked_until', '<', now()))
                ->whereIn('flow_id', DB::table('automation_flows')->where('status', FlowStatus::Active->value)->select('id'))
                ->orderBy('next_run_at')
                ->limit(self::CHUNK)
                ->lock($this->supportsSkipLocked() ? 'for update skip locked' : true)
                ->pluck('id');

            if ($ids->isEmpty()) {
                return [];
            }

            DB::table('automation_enrolments')
                ->whereIn('id', $ids)
                ->update([
                    'locked_until' => now()->addMinutes(5),
                    'lock_token'   => $token,
                ]);

            return DB::table('automation_enrolments')
                ->whereIn('id', $ids)
                ->where('lock_token', $token)
                ->get(['id', 'lock_token'])
                ->all();
        });
    }

    /**
     * Whether the connection understands SKIP LOCKED, so concurrent
     * dispatchers claim disjoint rows instead of queueing on each other's
     * row locks. MySQL 8+ and PostgreSQL do; MariaDB only from 10.6; SQLite never.
     */
    protected function supportsSkipLocked(): bool
    {
        $connection = DB::connection();

        return match ($connection->getDriverName()) {
            'pgsql'   => true,
            'mysql'   => $connection->isMaria()
                ? version_compare($connection->getServerVersion(), '10.6', '>=')
                : version_compare($connection->getServerVersion(), '8.0.1', '>='),
            'mariadb' => version_compare($connection->getServerVersion(), '10.6', '>='),
            default   => false,
        };
    }
}
