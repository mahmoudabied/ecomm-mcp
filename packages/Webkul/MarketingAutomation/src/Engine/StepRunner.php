<?php

namespace Webkul\MarketingAutomation\Engine;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Webkul\Core\Models\Channel;
use Webkul\MarketingAutomation\Enums\EnrolmentStatus;
use Webkul\MarketingAutomation\Enums\FlowStatus;
use Webkul\MarketingAutomation\Enums\JourneyEvent;
use Webkul\MarketingAutomation\Enums\SkipReason;
use Webkul\MarketingAutomation\Enums\StepType;
use Webkul\MarketingAutomation\Models\Enrolment;
use Webkul\MarketingAutomation\Rendering\ContextBuilder;
use Webkul\MarketingAutomation\Services\Settings;

class StepRunner
{
    /**
     * Safety valve: the most steps one run may execute.
     */
    protected const MAX_STEPS_PER_RUN = 25;

    /**
     * The step types that deliver a message.
     *
     * @var array<int, string>
     */
    protected const SEND_STEP_TYPES = [
        StepType::SendEmail->value,
        StepType::SendSms->value,
        StepType::SendWhatsapp->value,
    ];

    /**
     * Create a new step runner instance.
     */
    public function __construct(
        protected ContextBuilder $contextBuilder,
        protected Settings $settings,
    ) {}

    /**
     * Run the enrolment's current step and follow the outcomes.
     */
    public function run(Enrolment $enrolment): void
    {
        $flow = $enrolment->flow;

        if ($flow->status !== FlowStatus::Active
            || $enrolment->status !== EnrolmentStatus::Active
        ) {
            return;
        }

        $context = $this->buildContext($enrolment);

        $definition = new FlowDefinition($enrolment->flowVersion->definition);

        $currentKey = (string) $enrolment->current_step_key;

        for ($executed = 0; $executed < self::MAX_STEPS_PER_RUN; $executed++) {
            $enrolment->refresh();

            if ($enrolment->status !== EnrolmentStatus::Active) {
                return;
            }

            if ($flow->fresh()->status !== FlowStatus::Active) {
                return;
            }

            if (! in_array($currentKey, $definition->keys(), true)) {
                $this->complete($enrolment);

                return;
            }

            $step = $definition->step($currentKey);

            if ($this->stepOverdueAfterResume($enrolment, $flow, $step)) {
                $currentKey = $this->skipExpired($enrolment, $definition, $currentKey);

                continue;
            }

            $context->currentStepKey = $currentKey;

            $outcome = $context->handlerFor($step)->handle($enrolment, $step, $context);

            $currentKey = match ($outcome->kind) {
                'next'   => $this->advanceOrComplete($enrolment, $outcome->nextKey),
                'wait'   => $this->waitAndStop($enrolment, JourneyEvent::Waited, null, $outcome->waitUntil, $outcome->nextKey ?? $currentKey),
                'defer'  => $this->waitAndStop($enrolment, JourneyEvent::Deferred, $outcome->reason, $outcome->waitUntil, $outcome->nextKey ?? $currentKey),
                'retry'  => $this->retryLater($enrolment, $outcome),
                'branch' => $this->branchAndContinue($enrolment, $currentKey, (bool) $outcome->branch, $definition),
                'exit'   => $this->exitAndStop($enrolment),
                default  => throw new \LogicException("Unknown step outcome [{$outcome->kind}]."),
            };

            if ($currentKey === '') {
                return;
            }
        }

        // Guard rail: reschedule instead of spinning inside a single run.
        $enrolment->update(['next_run_at' => now()]);
    }

    /**
     * Close out a step whose job retries are exhausted (FR-019): deliver the
     * configured fallback when there is one, otherwise advance to the next step.
     */
    public function finishFailedStep(Enrolment $enrolment): void
    {
        if ($enrolment->status !== EnrolmentStatus::Active) {
            return;
        }

        $definition = new FlowDefinition($enrolment->flowVersion->definition);

        $stepKey = (string) $enrolment->current_step_key;

        if (! in_array($stepKey, $definition->keys(), true)) {
            $this->complete($enrolment);

            return;
        }

        $step = $definition->step($stepKey);

        $context = $this->buildContext($enrolment);

        $context->currentStepKey = $stepKey;

        $outcome = app(Steps\SendStep::class)->deliverFallbackAfterExhaustion($enrolment, $step, $context);

        $nextKey = $outcome->nextKey ?? $definition->nextOf($stepKey);

        if ($nextKey === null || $nextKey === '') {
            $this->complete($enrolment);

            return;
        }

        $enrolment->forceFill([
            'current_step_key' => $nextKey,
            'next_run_at'      => now(),
            'locked_until'     => null,
        ])->save();
    }

    /**
     * Move to the following step, or finish the journey when there is none.
     */
    protected function advanceOrComplete(Enrolment $enrolment, ?string $nextKey): string
    {
        if ($nextKey === null || $nextKey === '') {
            $this->complete($enrolment);

            return '';
        }

        DB::transaction(fn () => $enrolment->update([
            'current_step_key' => $nextKey,
            'next_run_at'      => now(),
        ]));

        return $nextKey;
    }

    /**
     * Apply a wait or deferral outcome, log it once and stop the run.
     */
    protected function waitAndStop(Enrolment $enrolment, JourneyEvent $event, ?string $reason, $waitUntil, string $nextKey): string
    {
        DB::transaction(function () use ($enrolment, $event, $reason, $waitUntil, $nextKey) {
            $enrolment->events()->create([
                'step_key' => $enrolment->current_step_key,
                'event'    => $event->value,
                'reason'   => $reason,
            ]);

            $enrolment->update([
                'current_step_key' => $nextKey,
                'next_run_at'      => $waitUntil,
                'locked_until'     => null,
            ]);
        });

        return '';
    }

    /**
     * Release the enrolment until the backoff ends, staying on the same step.
     */
    protected function retryLater(Enrolment $enrolment, StepOutcome $outcome): string
    {
        DB::transaction(fn () => $enrolment->update([
            'next_run_at'  => $outcome->waitUntil,
            'locked_until' => $outcome->waitUntil,
        ]));

        return '';
    }

    /**
     * Record the condition branch and continue on that path.
     */
    protected function branchAndContinue(Enrolment $enrolment, string $stepKey, bool $branch, FlowDefinition $definition): string
    {
        $nextKey = (string) ($definition->nextOf($stepKey, $branch) ?? '');

        DB::transaction(function () use ($enrolment, $stepKey, $branch, $nextKey) {
            $enrolment->events()->create([
                'step_key' => $stepKey,
                'event'    => $branch ? JourneyEvent::ConditionYes->value : JourneyEvent::ConditionNo->value,
            ]);

            if ($nextKey === '') {
                $this->complete($enrolment);
            } else {
                $enrolment->update([
                    'current_step_key' => $nextKey,
                    'next_run_at'      => now(),
                ]);
            }
        });

        return $nextKey;
    }

    /**
     * Whether a paused flow left this send step waiting too long.
     */
    protected function stepOverdueAfterResume(Enrolment $enrolment, $flow, array $step): bool
    {
        if (! in_array($step['type'] ?? '', self::SEND_STEP_TYPES, true)) {
            return false;
        }

        if ($flow->resumed_at === null || $enrolment->next_run_at === null) {
            return false;
        }

        if ($enrolment->next_run_at->greaterThanOrEqualTo($flow->resumed_at)) {
            return false;
        }

        return $enrolment->next_run_at->diffInHours($flow->resumed_at) > 24;
    }

    /**
     * Mark a step skipped because it expired during a pause, persisting the move.
     *
     * @return string The next step key, or an empty string when the journey ends.
     */
    protected function skipExpired(Enrolment $enrolment, FlowDefinition $definition, string $stepKey): string
    {
        $nextKey = (string) ($definition->nextOf($stepKey) ?? '');

        DB::transaction(function () use ($enrolment, $stepKey, $nextKey) {
            $enrolment->events()->create([
                'step_key' => $stepKey,
                'event'    => JourneyEvent::Skipped->value,
                'reason'   => SkipReason::Expired->value,
            ]);

            if ($nextKey === '') {
                $this->complete($enrolment);
            } else {
                $enrolment->update([
                    'current_step_key' => $nextKey,
                    'next_run_at'      => now(),
                ]);
            }
        });

        return $nextKey;
    }

    /**
     * Handle an explicit exit step and stop.
     */
    protected function exitAndStop(Enrolment $enrolment): string
    {
        DB::transaction(function () use ($enrolment) {
            $enrolment->events()->create([
                'step_key' => $enrolment->current_step_key,
                'event'    => JourneyEvent::Exited->value,
            ]);

            $this->complete($enrolment);
        });

        return '';
    }

    /**
     * Move the enrolment into the completed state.
     */
    protected function complete(Enrolment $enrolment): void
    {
        $enrolment->update([
            'status'       => EnrolmentStatus::Completed->value,
            'completed_at' => now(),
            'next_run_at'  => null,
        ]);

        Event::dispatch('marketing_automation.enrolment.finish.after', [$enrolment]);
    }

    /**
     * Assemble the execution context for the enrolment.
     */
    protected function buildContext(Enrolment $enrolment): StepContext
    {
        /** @var Channel $channel */
        $channel = Channel::query()->findOrFail($enrolment->channel_id);

        return new StepContext(
            flow: $enrolment->flow,
            flowVersion: $enrolment->flowVersion,
            enrolment: $enrolment,
            contact: $enrolment->contact,
            channel: $channel,
            timezone: $this->settings->timezone($channel),
            contextBuilder: $this->contextBuilder,
        );
    }
}
