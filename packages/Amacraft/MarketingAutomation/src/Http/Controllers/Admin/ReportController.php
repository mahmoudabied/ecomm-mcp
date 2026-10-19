<?php

namespace Amacraft\MarketingAutomation\Http\Controllers\Admin;

use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Amacraft\MarketingAutomation\DataGrids\EnrolmentDataGrid;
use Amacraft\MarketingAutomation\Models\Enrolment;
use Amacraft\MarketingAutomation\Models\Flow;
use Amacraft\MarketingAutomation\Models\MessageLog;
use Amacraft\MarketingAutomation\Repositories\StepStatRepository;
use Amacraft\MarketingAutomation\Services\RecipientMasker;

class ReportController extends Controller
{
    /**
     * Create a new report controller instance.
     */
    public function __construct(
        protected StepStatRepository $stepStatRepository,
        protected RecipientMasker $recipientMasker,
    ) {}

    /**
     * The per-step performance of one flow over a date range; JSON for the
     * API, the report page for browsers.
     */
    public function report(Flow $flow)
    {
        $this->authorizeReports();

        [$from, $to] = $this->range(request('from'), request('to'));

        $channelId = request('channel_id') !== null && request('channel_id') !== ''
            ? (int) request('channel_id')
            : null;

        $data = $this->stepStatRepository->report((int) $flow->id, $from, $to, $channelId);

        if (request()->wantsJson() || request()->ajax()) {
            return response()->json(['data' => $data]);
        }

        return view('marketing_automation::admin.flows.report', [
            'flow'  => $flow,
            'from'  => $from,
            'to'    => $to,
            'kpis'  => $this->kpis($data),
            'steps' => $this->stepRows($data),
        ]);
    }

    /**
     * The enrolments of one flow, feeding the report DataGrid. The flow the
     * grid reports on is read from the request inside the DataGrid.
     */
    public function enrolments(): JsonResponse
    {
        $this->authorizeReports();

        return app(EnrolmentDataGrid::class)->toJson();
    }

    /**
     * The full journey of one enrolment, newest last, with masked recipients.
     */
    public function journey(Enrolment $enrolment): JsonResponse
    {
        $this->authorizeReports();

        $events = $enrolment->events()
            ->with('messageLog')
            ->orderBy('id')
            ->get()
            ->map(fn ($event) => [
                'step_key'   => $event->step_key,
                'event'      => $event->event,
                'reason'     => $event->reason,
                'message'    => $this->messageSummary($event->messageLog),
                'created_at' => (string) $event->created_at,
            ]);

        return response()->json([
            'data' => [
                'enrolment' => [
                    'id'               => $enrolment->id,
                    'status'           => $enrolment->status->value,
                    'current_step_key' => $enrolment->current_step_key,
                    'created_at'       => (string) $enrolment->created_at,
                ],
                'events' => $events,
            ],
        ]);
    }

    /**
     * The masked essentials of the message a journey entry refers to.
     */
    protected function messageSummary(?MessageLog $log): ?array
    {
        if ($log === null) {
            return null;
        }

        return [
            'channel'          => $log->message_channel->value,
            'status'           => $log->status->value,
            'error_message'    => $log->error_message,
            'recipient_masked' => $this->recipientMasker->mask($log->recipient),
        ];
    }

    /**
     * Parse the requested date range, defaulting to the last thirty days.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    protected function range(?string $from, ?string $to): array
    {
        $from = $from !== null && strtotime($from) !== false
            ? Carbon::parse($from)->startOfDay()
            : now()->subDays(30)->startOfDay();

        $to = $to !== null && strtotime($to) !== false
            ? Carbon::parse($to)->endOfDay()
            : now()->endOfDay();

        return [$from, $to];
    }

    /**
     * The headline numbers of the report page.
     *
     * @param  array{flow: array<string, float|int>}  $data
     * @return array<string, string|int|float>
     */
    protected function kpis(array $data): array
    {
        $flow = $data['flow'];

        return [
            'enrolled'  => (int) $flow['enrolled'],
            'sent'      => (int) $flow['sent'],
            'delivered' => (int) $flow['delivered'],
            'openRate'  => $this->rate($flow['opened'], $flow['sent']),
            'clickRate' => $this->rate($flow['clicked'], $flow['sent']),
            'converted' => (int) $flow['converted'],
            'revenue'   => core()->formatPrice((float) $flow['revenue']),
        ];
    }

    /**
     * The per-step table rows of the report page, rates and skip reasons
     * precomputed.
     *
     * @param  array{steps: array<string, array<string, mixed>>}  $data
     * @return array<int, array<string, mixed>>
     */
    protected function stepRows(array $data): array
    {
        return collect($data['steps'])
            ->map(function (array $step) {
                $step['type_label'] = $step['type'] === 'removed'
                    ? trans('marketing_automation::app.reports.removed')
                    : ($this->stepTypeLabels()[$step['type']] ?? $step['type']);

                $step['open_rate'] = $this->rate($step['opened'], $step['sent']);

                $step['click_rate'] = $this->rate($step['clicked'], $step['sent']);

                $step['revenue_formatted'] = core()->formatPrice((float) $step['revenue']);

                $step['skipped_title'] = collect($step['skipped_by_reason'])
                    ->map(fn (int $count, string $reason) => "{$reason}: {$count}")
                    ->implode(', ');

                return $step;
            })
            ->values()
            ->all();
    }

    /**
     * A share of a base count as a percentage string.
     */
    protected function rate(int|float $part, int|float $base): string
    {
        if ((int) $base === 0) {
            return '—';
        }

        return round($part / $base * 100, 1).' %';
    }

    /**
     * The translated step type labels.
     *
     * @return array<string, string>
     */
    protected function stepTypeLabels(): array
    {
        return [
            'wait'          => trans('marketing_automation::app.flows.steps.wait'),
            'condition'     => trans('marketing_automation::app.flows.steps.condition'),
            'send_email'    => trans('marketing_automation::app.flows.steps.send_email'),
            'send_sms'      => trans('marketing_automation::app.flows.steps.send_sms'),
            'send_whatsapp' => trans('marketing_automation::app.flows.steps.send_whatsapp'),
            'exit'          => trans('marketing_automation::app.flows.steps.exit'),
        ];
    }

    /**
     * Guard the report actions.
     */
    protected function authorizeReports(): void
    {
        if (! bouncer()->hasPermission('marketing.automation.reports')) {
            abort(401, 'This action is unauthorized.');
        }
    }
}
