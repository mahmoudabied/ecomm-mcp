<?php

namespace Webkul\MarketingAutomation\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\MarketingAutomation\Database\Seeders\FlowTemplates;
use Webkul\MarketingAutomation\DataGrids\FlowDataGrid;
use Webkul\MarketingAutomation\Enums\FlowStatus;
use Webkul\MarketingAutomation\Enums\TriggerType;
use Webkul\MarketingAutomation\Enums\WhatsAppTemplateStatus;
use Webkul\MarketingAutomation\Http\Requests\FlowRequest;
use Webkul\MarketingAutomation\Models\Flow;
use Webkul\MarketingAutomation\Models\WhatsAppTemplate;
use Webkul\MarketingAutomation\Rendering\ContextBuilder;
use Webkul\MarketingAutomation\Rendering\MessageRenderer;
use Webkul\MarketingAutomation\Repositories\FlowRepository;

class FlowController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct(
        protected FlowRepository $flowRepository,
    ) {}

    /**
     * Show the flows overview, or feed the DataGrid over AJAX.
     */
    public function index()
    {
        $this->authorizeRead();

        if (request()->ajax()) {
            return app(FlowDataGrid::class)->toJson();
        }

        return view('marketing_automation::admin.flows.index', [
            'templates' => $this->templatesForView(),
        ]);
    }

    /**
     * List the prebuilt flow templates.
     */
    public function templates(): JsonResponse
    {
        $this->authorizeManage();

        return response()->json([
            'message' => '',
            'data'    => array_values($this->templatesForView()),
        ]);
    }

    /**
     * Store a newly created flow (status draft).
     */
    public function store(FlowRequest $request): JsonResponse
    {
        $this->authorizeManage();

        $flow = $this->flowRepository->create([
            ...$this->flowAttributes($request),
            'template_key' => $request->input('template_key'),
            'status'       => FlowStatus::Draft->value,
        ]);

        if ($template = FlowTemplates::find((string) $request->input('template_key'))) {
            $flow->update(['draft_definition' => $template['draft_definition']]);
        }

        $flow->channels()->sync($request->input('channel_ids'));

        return response()->json([
            'message' => trans('marketing_automation::app.flows.created'),
            'data'    => $this->present($flow),
        ], 201);
    }

    /**
     * Show the flow builder.
     */
    public function edit(Flow $flow)
    {
        $this->authorizeRead();

        $whatsappTemplates = $flow->channels()->exists()
            ? WhatsAppTemplate::query()
                ->whereIn('provider_connection_id', function ($query) use ($flow) {
                    $query->select('provider_connection_id')
                        ->from('automation_provider_channel_defaults')
                        ->whereIn('channel_id', $flow->channels->pluck('id'));
                })
                ->where('status', WhatsAppTemplateStatus::Approved->value)
                ->get()
            : collect();

        return view('marketing_automation::admin.flows.edit', [
            'flow'              => $flow,
            'draftDefinition'   => $flow->draft_definition ?? [],
            'whatsappTemplates' => $whatsappTemplates,
            'builderFlow'       => [
                'id'                   => $flow->id,
                'name'                 => $flow->name,
                'trigger_type'         => $flow->trigger_type->value,
                'trigger_config'       => $flow->trigger_config ?? [],
                'type'                 => $flow->type->value,
                'status'               => $flow->status->value,
                'channel_ids'          => $flow->channels->pluck('id')->all(),
                'reentry_window_hours' => $flow->reentry_window_hours,
                'exit_goals'           => $flow->exit_goals ?? [],
            ],
            'builderDefinition' => $flow->draft_definition ?: [
                'entry' => 's1',
                'steps' => [
                    's1' => [
                        'type'    => 'send_email',
                        'subject' => ['en' => '', 'ar' => ''],
                        'blocks'  => [
                            'en' => [['type' => 'text', 'text' => '']],
                            'ar' => [['type' => 'text', 'text' => '']],
                        ],
                        'validity_hours' => 24,
                        'next'           => 's2',
                    ],
                    's2' => ['type' => 'exit'],
                ],
            ],
            'builderTemplates'  => $whatsappTemplates
                ->map(fn ($template) => [
                    'id'   => $template->id,
                    'name' => $template->name.' ('.$template->language.', '.$template->variable_count.' vars)',
                ])
                ->values()
                ->all(),
            'builderChannels'   => core()->getAllChannels()
                ->map(fn ($channel) => ['id' => $channel->id, 'name' => $channel->name])
                ->values()
                ->all(),
            'triggerLabels'     => collect(TriggerType::cases())
                ->mapWithKeys(fn ($case) => [$case->value => trans("marketing_automation::app.flows.triggers.{$case->value}")])
                ->all(),
        ]);
    }

    /**
     * Update the flow settings and its draft definition.
     */
    public function update(FlowRequest $request, Flow $flow): JsonResponse
    {
        if ($flow->status === FlowStatus::Active && $this->activeFlowWouldChange($flow, $request)) {
            return response()->json([
                'message' => trans('marketing_automation::app.flows.active-immutable'),
            ], 422);
        }

        $flow->update($this->flowAttributes($request));

        $flow->channels()->sync($request->input('channel_ids'));

        return response()->json([
            'message' => trans('marketing_automation::app.flows.updated'),
            'data'    => $this->present($flow),
        ]);
    }

    /**
     * Validate and publish the draft as a new version.
     */
    public function activate(Flow $flow): JsonResponse
    {
        $this->authorizeManage();

        try {
            $version = $this->flowRepository->activate($flow);
        } catch (ValidationException $exception) {
            return response()->json([
                'message' => trans('marketing_automation::app.flows.validation-failed'),
                'errors'  => $exception->errors(),
            ], 422);
        }

        return response()->json([
            'message' => trans('marketing_automation::app.flows.activated'),
            'data'    => ['status' => $flow->refresh()->status->value, 'version' => $version->version],
        ]);
    }

    /**
     * Pause the flow.
     */
    public function pause(Flow $flow): JsonResponse
    {
        $this->authorizeManage();

        if ($flow->status !== FlowStatus::Active) {
            return response()->json([
                'message' => trans('marketing_automation::app.flows.not-active'),
            ], 422);
        }

        $this->flowRepository->pause($flow);

        return response()->json([
            'message' => trans('marketing_automation::app.flows.paused'),
            'data'    => ['status' => $flow->refresh()->status->value],
        ]);
    }

    /**
     * Resume the flow.
     */
    public function resume(Flow $flow): JsonResponse
    {
        $this->authorizeManage();

        if ($flow->status !== FlowStatus::Paused) {
            return response()->json([
                'message' => trans('marketing_automation::app.flows.not-paused'),
            ], 422);
        }

        $this->flowRepository->resume($flow);

        return response()->json([
            'message' => trans('marketing_automation::app.flows.resumed'),
            'data'    => ['status' => $flow->refresh()->status->value],
        ]);
    }

    /**
     * Duplicate the flow into a draft copy.
     */
    public function duplicate(Flow $flow): JsonResponse
    {
        $this->authorizeManage();

        $copy = $this->flowRepository->duplicate($flow);

        return response()->json([
            'message' => trans('marketing_automation::app.flows.duplicated'),
            'data'    => $this->present($copy),
        ], 201);
    }

    /**
     * Delete the flow, cancelling its running enrolments.
     */
    public function destroy(Flow $flow): JsonResponse
    {
        $this->authorizeManage();

        $this->flowRepository->deleteFlow($flow);

        return response()->json([
            'message' => trans('marketing_automation::app.flows.deleted'),
        ]);
    }

    /**
     * Render one step of the draft with sample or customer data.
     */
    public function preview(Flow $flow, string $stepKey)
    {
        $this->authorizeRead();

        $context = app(ContextBuilder::class)
            ->forSample($flow->channels->first()?->id ?? (int) core()->getDefaultChannel()?->id, request('locale', config('app.locale')));

        $step = ($flow->draft_definition ?? [])['steps'][$stepKey] ?? null;

        if ($step === null) {
            abort(404);
        }

        $renderer = app(MessageRenderer::class);

        return match ($step['type'] ?? '') {
            'send_sms' => response($renderer->renderText($step, $context, request('locale', config('app.locale'))), 200, ['Content-Type' => 'text/plain']),
            default    => response($renderer->renderEmail($step, $context, request('locale', config('app.locale')))['html']),
        };
    }

    /**
     * Guard the read actions.
     */
    protected function authorizeRead(): void
    {
        if (! bouncer()->hasPermission('marketing.automation.flows')) {
            abort(401, 'This action is unauthorized.');
        }
    }

    /**
     * Guard the write actions.
     */
    protected function authorizeManage(): void
    {
        if (! bouncer()->hasPermission('marketing.automation.flows.manage')) {
            abort(401, 'This action is unauthorized.');
        }
    }

    /**
     * The persisted attributes of a flow request.
     */
    protected function flowAttributes(FlowRequest $request): array
    {
        $attributes = [
            'name'                 => $request->input('name'),
            'trigger_type'         => $request->input('trigger_type'),
            'trigger_config'       => $request->input('trigger_config', []),
            'type'                 => $request->input('type'),
            'reentry_window_hours' => (int) $request->input('reentry_window_hours', 168),
            'exit_goals'           => $request->input('exit_goals', []),
        ];

        if ($request->filled('draft_definition')) {
            $attributes['draft_definition'] = $request->input('draft_definition');
        }

        return $attributes;
    }

    /**
     * Whether the request would repoint an active flow's trigger, type or channels.
     */
    protected function activeFlowWouldChange(Flow $flow, FlowRequest $request): bool
    {
        if ($flow->trigger_type->value !== $request->input('trigger_type')
            || $flow->type->value !== $request->input('type')
        ) {
            return true;
        }

        $currentChannelIds = $flow->channels->pluck('id')->sort()->values()->all();

        $requestedChannelIds = collect((array) $request->input('channel_ids'))
            ->map(fn ($id) => (int) $id)
            ->sort()
            ->values()
            ->all();

        return $currentChannelIds !== $requestedChannelIds;
    }

    /**
     * Templates with their labels resolved for JSON output.
     *
     * @return array<int, array>
     */
    protected function templatesForView(): array
    {
        return array_map(fn (array $template) => [
            'key'          => $template['key'],
            'name'         => trans($template['name']),
            'description'  => trans($template['description']),
            'trigger_type' => $template['trigger_type'],
            'type'         => $template['type'],
        ], FlowTemplates::all());
    }

    /**
     * Present a flow without heavy columns.
     */
    protected function present(Flow $flow): array
    {
        return [
            'id'                   => $flow->id,
            'name'                 => $flow->name,
            'template_key'         => $flow->template_key,
            'trigger_type'         => $flow->trigger_type->value,
            'type'                 => $flow->type->value,
            'status'               => $flow->status->value,
            'channel_ids'          => $flow->channels->pluck('id')->all(),
            'reentry_window_hours' => $flow->reentry_window_hours,
            'exit_goals'           => $flow->exit_goals,
            'draft_definition'     => $flow->draft_definition,
        ];
    }
}
