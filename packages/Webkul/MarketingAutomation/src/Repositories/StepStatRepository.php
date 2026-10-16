<?php

namespace Webkul\MarketingAutomation\Repositories;

use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Webkul\Core\Eloquent\Repository;
use Webkul\MarketingAutomation\Engine\FlowDefinition;
use Webkul\MarketingAutomation\Models\Flow;

class StepStatRepository extends Repository
{
    /**
     * The metrics the report exposes, in display order.
     *
     * @var array<int, string>
     */
    protected const METRICS = [
        'enrolled', 'sent', 'delivered', 'opened', 'clicked', 'skipped', 'failed', 'unsubscribed', 'converted',
    ];

    /**
     * Specify model class name.
     */
    public function model(): string
    {
        return 'Webkul\MarketingAutomation\Contracts\StepStat';
    }

    /**
     * The flow report over a date range: flow totals plus one row per step,
     * with the skip reasons broken out. Steps that only older versions had
     * are listed with the type "removed"; the flow-level `_flow` mirror rows
     * are excluded so totals are not counted twice.
     *
     * @return array{flow: array<string, float|int>, steps: array<string, array<string, mixed>>}
     */
    public function report(int $flowId, Carbon $from, Carbon $to, ?int $channelId): array
    {
        $rows = $this->baseQuery($flowId, $from, $to, $channelId)
            ->groupBy('step_key', 'metric', 'skip_reason')
            ->get([
                'step_key',
                'metric',
                'skip_reason',
                DB::raw('SUM(count) as count'),
                DB::raw('SUM(revenue) as revenue'),
            ]);

        $flow = array_fill_keys(self::METRICS, 0);

        $flow['revenue'] = 0.0;

        $steps = [];

        $types = $this->stepTypes((int) $flowId);

        foreach ($rows as $row) {
            $steps[$row->step_key] ??= [
                'step_key'          => $row->step_key,
                'type'              => $types[$row->step_key] ?? 'removed',
                'revenue'           => 0.0,
                'skipped_by_reason' => [],
                ...array_fill_keys(self::METRICS, 0),
            ];

            $step = &$steps[$row->step_key];

            if (in_array($row->metric, self::METRICS, true)) {
                $flow[$row->metric] += (int) $row->count;

                $step[$row->metric] += (int) $row->count;
            }

            $flow['revenue'] += (float) $row->revenue;

            $step['revenue'] += (float) $row->revenue;

            if (
                $row->metric === 'skipped'
                && (string) $row->skip_reason !== ''
            ) {
                $step['skipped_by_reason'][$row->skip_reason] = (int) $row->count;
            }

            unset($step);
        }

        return ['flow' => $flow, 'steps' => $steps];
    }

    /**
     * The rollup rows of the flow inside the range.
     */
    protected function baseQuery(int $flowId, Carbon $from, Carbon $to, ?int $channelId): Builder
    {
        return DB::table('automation_step_stats')
            ->where('flow_id', $flowId)
            ->where('step_key', '!=', '_flow')
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->when($channelId !== null, fn ($query) => $query->where('channel_id', $channelId));
    }

    /**
     * The step types of the flow's current version definition.
     *
     * @return array<string, string>
     */
    protected function stepTypes(int $flowId): array
    {
        $flow = Flow::query()->with('currentVersion')->find($flowId);

        $definition = $flow?->currentVersion?->definition;

        if (! is_array($definition)) {
            return [];
        }

        try {
            $graph = new FlowDefinition($definition);
        } catch (\InvalidArgumentException) {
            return [];
        }

        return collect($graph->keys())
            ->mapWithKeys(fn (string $key) => [$key => (string) $graph->step($key)['type']])
            ->all();
    }
}
