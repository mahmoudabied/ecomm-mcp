<?php

namespace Webkul\MarketingAutomation\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Webkul\Core\Models\Channel;

class StatsRecorder
{
    /**
     * The flow-level step key that aggregates enrolment and conversion totals.
     */
    protected const FLOW_STEP_KEY = '_flow';

    /**
     * Timezones of the channels seen so far, so a run that writes many
     * rollups resolves each channel once.
     *
     * @var array<int, string>
     */
    protected array $channelTimezones = [];

    /**
     * Create a new stats recorder instance.
     */
    public function __construct(
        protected Settings $settings,
    ) {}

    /**
     * Add to the daily rollup of one step. The metrics `enrolled` and
     * `converted` are mirrored onto the flow-level `_flow` row.
     */
    public function increment(
        int $flowId,
        string $stepKey,
        int $channelId,
        string $metric,
        string $skipReason = '',
        int $by = 1,
        float $revenue = 0.0,
    ): void {
        $this->upsert($flowId, $stepKey, $channelId, $metric, $skipReason, $by, $revenue);

        if (in_array($metric, ['enrolled', 'converted'], true)) {
            $this->upsert($flowId, self::FLOW_STEP_KEY, $channelId, $metric, '', $by, $revenue);
        }
    }

    /**
     * Insert the rollup row or add to the existing one.
     */
    protected function upsert(
        int $flowId,
        string $stepKey,
        int $channelId,
        string $metric,
        string $skipReason,
        int $by,
        float $revenue,
    ): void {
        DB::table('automation_step_stats')->upsert(
            [
                [
                    'date'        => $this->today($channelId),
                    'flow_id'     => $flowId,
                    'step_key'    => $stepKey,
                    'channel_id'  => $channelId,
                    'metric'      => $metric,
                    'skip_reason' => $skipReason,
                    'count'       => $by,
                    'revenue'     => $revenue,
                ],
            ],
            ['date', 'flow_id', 'step_key', 'channel_id', 'metric', 'skip_reason'],
            [
                'count'   => DB::raw('count + '.$by),
                'revenue' => DB::raw('revenue + '.$revenue),
            ],
        );
    }

    /**
     * Today in the sales channel's timezone, so rollups match local days.
     */
    protected function today(int $channelId): string
    {
        if (! isset($this->channelTimezones[$channelId])) {
            $channel = Channel::query()->find($channelId);

            $this->channelTimezones[$channelId] = $channel
                ? $this->settings->timezone($channel)
                : (string) config('app.timezone');
        }

        return Carbon::now($this->channelTimezones[$channelId])->toDateString();
    }
}
