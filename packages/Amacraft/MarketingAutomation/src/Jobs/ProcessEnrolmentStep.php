<?php

namespace Amacraft\MarketingAutomation\Jobs;

use Amacraft\MarketingAutomation\Engine\StepRunner;
use Amacraft\MarketingAutomation\Enums\EnrolmentStatus;
use Amacraft\MarketingAutomation\Enums\JourneyEvent;
use Amacraft\MarketingAutomation\Enums\MessageStatus;
use Amacraft\MarketingAutomation\Models\Enrolment;
use Amacraft\MarketingAutomation\Models\MessageLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Throwable;

class ProcessEnrolmentStep implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 8;

    /**
     * Extra lock time beyond the retry backoff, covering queue pickup delay.
     */
    protected const LOCK_MARGIN_SECONDS = 300;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $enrolmentId,
        public ?string $lockToken = null,
    ) {
        $this->onQueue(config('marketing-automation.queues.automation'));
    }

    /**
     * The backoff schedule for retried attempts.
     *
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [60, 300, 900, 1800, 3600, 7200, 14400];
    }

    /**
     * Give up after a day of retries.
     */
    public function retryUntil(): \DateTimeInterface
    {
        return now()->addHours(24);
    }

    /**
     * Run the enrolment's current step.
     */
    public function handle(StepRunner $runner): void
    {
        /** @var ?Enrolment $enrolment */
        $enrolment = Enrolment::query()->find($this->enrolmentId);

        if ($enrolment === null) {
            return;
        }

        if ($this->lockToken !== null) {
            if ($enrolment->lock_token !== $this->lockToken) {
                return;
            }
        } elseif (! $this->claimAtomically($enrolment)) {
            return;
        }

        $this->recordDispatchLag($enrolment);

        try {
            $runner->run($enrolment);
        } catch (Throwable $exception) {
            $this->holdLockThroughBackoff($enrolment);

            throw $exception;
        }

        $this->releaseLock($enrolment);
    }

    /**
     * Keep this job's claim past the coming retry backoff. Otherwise the
     * five-minute dispatcher lock lapses mid-backoff, the dispatcher claims
     * the row under a new token, this retry bails out on the token mismatch
     * and failed() is never reached. failed() releases the claim at the end.
     */
    protected function holdLockThroughBackoff(Enrolment $enrolment): void
    {
        $schedule = $this->backoff();

        $delay = $schedule[min(max($this->attempts(), 1), count($schedule)) - 1];

        Enrolment::query()
            ->whereKey($enrolment->id)
            ->where('lock_token', $enrolment->lock_token)
            ->update(['locked_until' => now()->addSeconds($delay + self::LOCK_MARGIN_SECONDS)]);
    }

    /**
     * Wrap up a permanently failing journey (FR-019): record the failure,
     * then apply the step's fallback channel, or advance — never loop.
     */
    public function failed(Throwable $exception): void
    {
        /** @var ?Enrolment $enrolment */
        $enrolment = Enrolment::query()->find($this->enrolmentId);

        if ($enrolment === null) {
            return;
        }

        $log = MessageLog::query()
            ->where('enrolment_id', $enrolment->id)
            ->where('status', MessageStatus::Queued->value)
            ->orderByDesc('id')
            ->first();

        $log?->update([
            'status'        => MessageStatus::Failed->value,
            'error_message' => $log->error_message ?? 'retries exhausted',
        ]);

        if ($enrolment->status === EnrolmentStatus::Active) {
            $enrolment->events()->firstOrCreate([
                'step_key'       => $enrolment->current_step_key,
                'event'          => JourneyEvent::Failed->value,
                'reason'         => 'retries_exhausted',
                'message_log_id' => $log?->id,
            ]);
        }

        try {
            app(StepRunner::class)->finishFailedStep($enrolment);
        } catch (Throwable) {
            $enrolment->forceFill(['next_run_at' => now(), 'locked_until' => null, 'lock_token' => null])->save();
        }

        $this->releaseLock($enrolment);
    }

    /**
     * Claim an unclaimed enrolment row so only one worker proceeds.
     */
    protected function claimAtomically(Enrolment $enrolment): bool
    {
        $token = (string) Str::ulid();

        $claimed = Enrolment::query()
            ->whereKey($this->enrolmentId)
            ->where('status', EnrolmentStatus::Active->value)
            ->where(fn ($query) => $query
                ->whereNull('lock_token')
                ->orWhere('locked_until', '<', now()))
            ->update([
                'lock_token'   => $token,
                'locked_until' => now()->addMinutes(5),
            ]);

        if ($claimed === 0) {
            return false;
        }

        $enrolment->refresh();

        return $enrolment->lock_token === $token;
    }

    /**
     * Clear the claim so the dispatcher can pick the enrolment up again,
     * keeping a backoff lock that the retry outcome scheduled into the future.
     */
    protected function releaseLock(Enrolment $enrolment): void
    {
        Enrolment::query()
            ->whereKey($enrolment->id)
            ->where('lock_token', $enrolment->lock_token)
            ->update(['lock_token' => null]);

        Enrolment::query()
            ->whereKey($enrolment->id)
            ->where('locked_until', '<=', now())
            ->update(['locked_until' => null]);
    }

    /**
     * Track how late the scheduler is running (SC-003); a comparison, since diffIn* is signed on Carbon 3.
     */
    protected function recordDispatchLag(Enrolment $enrolment): void
    {
        Cache::increment('marketing_automation.lag.total');

        if (
            $enrolment->next_run_at !== null
            && $enrolment->next_run_at->lessThan(now()->subSeconds(300))
        ) {
            Cache::increment('marketing_automation.lag.over_5m');
        }
    }
}
