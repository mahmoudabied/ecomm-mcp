<?php

namespace Amacraft\MarketingAutomation\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;
use Webkul\Admin\Http\Controllers\Controller;
use Amacraft\MarketingAutomation\Contracts\Providers\WhatsAppTemplateSource;
use Amacraft\MarketingAutomation\DataGrids\ProviderDataGrid;
use Amacraft\MarketingAutomation\Enums\MessageChannel;
use Amacraft\MarketingAutomation\Enums\ProviderStatus;
use Amacraft\MarketingAutomation\Enums\SendStatus;
use Amacraft\MarketingAutomation\Enums\WhatsAppTemplateStatus;
use Amacraft\MarketingAutomation\Http\Requests\ProviderConnectionRequest;
use Amacraft\MarketingAutomation\Http\Requests\ProviderDefaultRequest;
use Amacraft\MarketingAutomation\Http\Requests\ProviderTestRequest;
use Amacraft\MarketingAutomation\Jobs\SyncWhatsAppTemplates;
use Amacraft\MarketingAutomation\Models\ProviderConnection;
use Amacraft\MarketingAutomation\Repositories\ProviderConnectionRepository;
use Amacraft\MarketingAutomation\Services\PhoneNormalizer;
use Amacraft\MarketingAutomation\Services\ProviderManager;

class ProviderController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct(
        protected ProviderConnectionRepository $providerRepository,
        protected ProviderManager $providerManager,
    ) {}

    /**
     * Show the providers overview, or feed the DataGrid over AJAX.
     */
    public function index(): View|JsonResponse
    {
        if (! bouncer()->hasPermission('marketing.automation.settings')) {
            abort(401, 'This action is unauthorized.');
        }

        if (request()->ajax()) {
            return app(ProviderDataGrid::class)->toJson();
        }

        return view('marketing_automation::admin.providers.index', [
            'smsDrivers'      => $this->providerManager->driversFor(MessageChannel::Sms),
            'whatsappDrivers' => $this->providerManager->driversFor(MessageChannel::Whatsapp),
            'channels'        => core()->getAllChannels()
                ->map(fn ($channel) => ['id' => $channel->id, 'name' => $channel->name])
                ->values()
                ->all(),
            'connections'     => $this->providerRepository->selectOptions(),
            'defaults'        => $this->providerRepository->defaultsMap(),
        ]);
    }

    /**
     * Store a newly created provider connection.
     */
    public function store(ProviderConnectionRequest $request): JsonResponse
    {
        $connection = $this->providerRepository->create([
            ...$request->validated(),
            'credentials' => $request->validatedCredentials(),
            'status'      => ProviderStatus::Untested->value,
        ]);

        return response()->json([
            'message' => trans('marketing_automation::app.providers.created'),
            'data'    => $this->present($connection),
        ], 201);
    }

    /**
     * Show one connection with its secrets masked.
     */
    public function show(ProviderConnection $provider): JsonResponse
    {
        $this->authorizeSettings();

        return response()->json([
            'message' => '',
            'data'    => [
                ...$provider->only(['id', 'name', 'message_channel', 'driver', 'sender', 'status', 'last_error', 'last_tested_at']),
                'credentials'  => $this->providerRepository->maskedCredentials($provider),
                'webhook_url'  => $this->webhookUrl($provider),
                'verify_token' => $provider->driver === 'meta_cloud' ? $provider->webhook_secret : null,
            ],
        ]);
    }

    /**
     * Update a connection; empty or masked secret inputs keep the stored values.
     */
    public function update(ProviderConnectionRequest $request, ProviderConnection $provider): JsonResponse
    {
        $connection = $this->providerRepository->updateWithSecrets(
            $provider,
            [...$request->validated(), 'credentials' => $request->input('credentials', [])],
        );

        return response()->json([
            'message' => trans('marketing_automation::app.providers.updated'),
            'data'    => $this->present($connection),
        ]);
    }

    /**
     * Delete a connection unless it backs an active flow's channel default.
     */
    public function destroy(ProviderConnection $provider): JsonResponse
    {
        $this->authorizeSettings();

        if ($this->providerRepository->isActiveFlowDefault($provider)) {
            return response()->json([
                'message' => trans('marketing_automation::app.providers.delete-blocked'),
            ], 422);
        }

        $this->providerRepository->delete($provider->id);

        return response()->json([
            'message' => trans('marketing_automation::app.providers.deleted'),
        ]);
    }

    /**
     * Probe the credentials with a real message to the given recipient.
     */
    public function test(ProviderTestRequest $request, ProviderConnection $provider, PhoneNormalizer $phoneNormalizer): JsonResponse
    {
        $this->authorizeSettings();

        $to = $phoneNormalizer->normalize($request->input('to'));

        if ($to === null) {
            return response()->json([
                'message' => trans('marketing_automation::app.providers.invalid-recipient'),
            ], 422);
        }

        $result = $this->providerManager->driver($provider)->testConnection($provider, $to);

        $provider->update([
            'status'         => $result->status === SendStatus::Accepted ? ProviderStatus::Connected->value : ProviderStatus::Error->value,
            'last_error'     => $result->errorMessage !== null ? Str::limit($result->errorMessage, 250) : null,
            'last_tested_at' => now(),
        ]);

        if ($result->status !== SendStatus::Accepted) {
            return response()->json([
                'message' => $result->errorMessage ?: trans('marketing_automation::app.providers.test-failed'),
            ], 422);
        }

        return response()->json([
            'message' => trans('marketing_automation::app.providers.test-success'),
            'data'    => [
                'status'              => ProviderStatus::Connected->value,
                'provider_message_id' => $result->providerMessageId,
            ],
        ]);
    }

    /**
     * Pull the WhatsApp templates of a connection into the local cache.
     */
    public function syncTemplates(ProviderConnection $provider): JsonResponse
    {
        $this->authorizeSettings();

        if (! $this->providerManager->driver($provider) instanceof WhatsAppTemplateSource) {
            return response()->json([
                'message' => trans('marketing_automation::app.providers.templates-not-supported'),
            ], 422);
        }

        try {
            Bus::dispatchSync(new SyncWhatsAppTemplates($provider));
        } catch (Throwable) {
            return response()->json([
                'message' => trans('marketing_automation::app.providers.templates-sync-failed'),
            ], 422);
        }

        $templates = $provider->templates()
            ->where('status', WhatsAppTemplateStatus::Approved->value)
            ->orderBy('name')
            ->get();

        return response()->json([
            'message' => trans('marketing_automation::app.providers.templates-synced', ['count' => $templates->count()]),
            'data'    => [
                'synced'    => $templates->count(),
                'templates' => $templates,
            ],
        ]);
    }

    /**
     * Store (or clear) the per-sales-channel default connection.
     */
    public function updateDefaults(ProviderDefaultRequest $request): JsonResponse
    {
        $this->providerRepository->setDefault(
            (int) $request->input('channel_id'),
            MessageChannel::from($request->input('message_channel')),
            $request->filled('provider_connection_id') ? (int) $request->input('provider_connection_id') : null,
        );

        return response()->json([
            'message' => trans('marketing_automation::app.providers.defaults-updated'),
        ]);
    }

    /**
     * Abort when the admin lacks the settings permission.
     */
    protected function authorizeSettings(): void
    {
        if (! bouncer()->hasPermission('marketing.automation.settings')) {
            abort(401, 'This action is unauthorized.');
        }
    }

    /**
     * Build the webhook URL; the secret rides along only for drivers
     * that authenticate by shared secret instead of a signature.
     */
    protected function webhookUrl(ProviderConnection $provider): string
    {
        $url = route('automation.webhooks.receive', [$provider->driver, $provider->uuid]);

        if ($this->providerManager->webhookAuth($provider) === 'secret') {
            $url .= '?s='.$provider->webhook_secret;
        }

        return $url;
    }

    /**
     * Present a connection without its secrets.
     */
    protected function present(ProviderConnection $provider): array
    {
        return [
            'id'              => $provider->id,
            'name'            => $provider->name,
            'message_channel' => $provider->message_channel->value,
            'driver'          => $provider->driver,
            'sender'          => $provider->sender,
            'status'          => $provider->status->value,
        ];
    }
}
