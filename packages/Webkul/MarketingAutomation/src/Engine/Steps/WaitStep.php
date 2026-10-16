<?php

namespace Webkul\MarketingAutomation\Engine\Steps;

use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Webkul\MarketingAutomation\Engine\StepContext;
use Webkul\MarketingAutomation\Engine\StepOutcome;
use Webkul\MarketingAutomation\Models\Enrolment;

class WaitStep implements StepHandler
{
    /**
     * Schedule the next run of the journey.
     */
    public function handle(Enrolment $enrolment, array $step, StepContext $context): StepOutcome
    {
        return StepOutcome::waitUntil(
            $this->resolveMoment($step, $context),
            $step['next'] ?? null,
        );
    }

    /**
     * Compute the moment the journey continues.
     *
     * The schedule is computed in the channel timezone, then normalised to
     * the application timezone so Eloquent stores and reads it consistently.
     */
    protected function resolveMoment(array $step, StepContext $context): CarbonImmutable
    {
        $nowLocal = Carbon::now($context->timezone);

        $moment = match ($step['mode'] ?? 'duration') {
            'until_time'    => $this->nextOccurrenceOfTime($nowLocal, (string) $step['time']),
            'until_weekday' => $this->nextWeekdayOccurrence($nowLocal, (array) ($step['weekdays'] ?? []), (string) ($step['time'] ?? '09:00')),
            default         => $nowLocal->copy()->addMinutes((int) ($step['minutes'] ?? 0))->toImmutable(),
        };

        return $moment->timezone(config('app.timezone'));
    }

    /**
     * Today at the given local time, or tomorrow when it already passed.
     */
    protected function nextOccurrenceOfTime(Carbon $nowLocal, string $time): CarbonImmutable
    {
        [$hour, $minute] = array_pad(explode(':', $time), 2, '0');

        $moment = $nowLocal->copy()->setTime((int) $hour, (int) $minute, 0);

        if ($moment->lessThanOrEqualTo($nowLocal)) {
            $moment->addDay();
        }

        return $moment->toImmutable();
    }

    /**
     * The next date whose ISO weekday is selected, at the given local time.
     *
     * @param  array<int, int>  $weekdays
     */
    protected function nextWeekdayOccurrence(Carbon $nowLocal, array $weekdays, string $time): CarbonImmutable
    {
        $weekdays = array_map('intval', $weekdays);

        if ($weekdays === []) {
            return $this->nextOccurrenceOfTime($nowLocal, $time);
        }

        [$hour, $minute] = array_pad(explode(':', $time), 2, '0');

        $moment = $nowLocal->copy()->setTime((int) $hour, (int) $minute, 0);

        for ($daysAhead = 0; $daysAhead < 8; $daysAhead++) {
            $candidate = $moment->copy()->addDays($daysAhead);

            if ($candidate->greaterThan($nowLocal) && in_array($candidate->isoWeekday(), $weekdays, true)) {
                return $candidate->toImmutable();
            }
        }

        return $moment->addWeek()->toImmutable();
    }
}
