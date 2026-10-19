<?php

namespace Amacraft\Klaviyo\Http\Controllers\Admin;

use Amacraft\Klaviyo\Jobs\HistoricalSync;
use Amacraft\Klaviyo\Models\Connection;
use Amacraft\Klaviyo\Models\SyncJob;
use Amacraft\Klaviyo\Repositories\ConnectionRepository;
use Amacraft\Klaviyo\Repositories\OutboxItemRepository;
use Amacraft\Klaviyo\Services\KlaviyoClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\Customer\Models\Customer;
use Webkul\Sales\Models\Order;

class KlaviyoController extends Controller
{
    /**
     * The entities a historical sync can cover.
     *
     * @var array<int, string>
     */
    protected const ENTITIES = ['customers', 'orders', 'catalog'];

    /**
     * Create a new Klaviyo controller instance.
     */
    public function __construct(
        protected ConnectionRepository $connections,
        protected OutboxItemRepository $outboxItems,
    ) {}

    /**
     * Connection status per channel, sync progress and outbox stats.
     *
     * @return View|JsonResponse
     */
    public function index(Request $request)
    {
        $this->authorizeKlaviyo();

        $connections = Connection::query()->with('channel')->get();

        $presented = $connections->map(fn (Connection $connection) => $this->present($connection));

        if ($request->expectsJson()) {
            return new JsonResponse(['data' => $presented->values()->all()]);
        }

        return view('klaviyo::admin.index', [
            'connections' => $presented->values()->all(),
            'syncJobs'    => SyncJob::query()
                ->whereIn('id', SyncJob::query()->selectRaw('max(id)')->groupBy('entity'))
                ->get()
                ->map(fn (SyncJob $job) => $this->presentJob($job))
                ->values()
                ->all(),
        ]);
    }

    /**
     * Validate the key against Klaviyo, then store the connection.
     */
    public function connect(Request $request): JsonResponse
    {
        $this->authorizeKlaviyo();

        $data = $request->validate([
            'channel_id' => ['required', 'exists:channels,id'],
            'api_key'    => ['required', 'string', 'max:255'],
            'public_key' => ['nullable', 'string', 'max:255'],
        ]);

        $client = new KlaviyoClient(new Connection([
            'channel_id' => (int) $data['channel_id'],
            'api_key'    => $data['api_key'],
        ]));

        $response = $client->request('GET', '/api/accounts');

        if (! $response->successful()) {
            $this->recordFailure((int) $data['channel_id'], $client->errorMessage($response));

            return new JsonResponse([
                'message' => $client->errorMessage($response),
            ], 422);
        }

        $connection = $this->connections->firstOrNewByChannel((int) $data['channel_id']);

        $connection->forceFill([
            'api_key'      => $data['api_key'],
            'public_key'   => $data['public_key'] ?? $connection->public_key,
            'status'       => 'connected',
            'last_error'   => null,
            'connected_at' => now(),
        ])->save();

        return new JsonResponse([
            'message' => trans('klaviyo::app.admin.connected'),
            'data'    => $this->present($connection),
        ]);
    }

    /**
     * Mark a stored connection failed without clobbering its key.
     */
    protected function recordFailure(int $channelId, string $error): void
    {
        Connection::query()
            ->where('channel_id', $channelId)
            ->first()
            ?->forceFill([
                'status'     => 'error',
                'last_error' => $error,
            ])
            ->save();
    }

    /**
     * Detach the channel's connection.
     */
    public function disconnect(Request $request): JsonResponse
    {
        $this->authorizeKlaviyo();

        $data = $request->validate([
            'channel_id' => ['required', 'exists:channels,id'],
        ]);

        $connection = Connection::query()->where('channel_id', (int) $data['channel_id'])->first();

        if ($connection !== null) {
            $connection->forceFill([
                'status'       => 'disconnected',
                'connected_at' => null,
            ])->save();
        }

        return new JsonResponse([
            'message' => trans('klaviyo::app.admin.disconnected'),
        ]);
    }

    /**
     * Start one historical sync job per requested entity.
     */
    public function sync(Request $request): JsonResponse
    {
        $this->authorizeKlaviyo();

        $data = $request->validate([
            'channel_id' => ['required', 'exists:channels,id'],
            'entities'   => ['required', 'array', 'min:1'],
            'entities.*' => [Rule::in(self::ENTITIES)],
        ]);

        $connection = Connection::query()
            ->where('channel_id', (int) $data['channel_id'])
            ->where('status', 'connected')
            ->first();

        if ($connection === null) {
            return new JsonResponse([
                'message' => trans('klaviyo::app.admin.no-connection'),
            ], 422);
        }

        $jobs = collect($data['entities'])->map(function (string $entity) use ($connection) {
            return SyncJob::query()->create([
                'connection_id' => $connection->id,
                'entity'        => $entity,
                'status'        => 'queued',
                'total'         => $this->countEntity($connection, $entity),
            ]);
        });

        foreach ($jobs as $job) {
            HistoricalSync::dispatch($job->id)->onQueue(config('marketing-automation.queues.klaviyo'));
        }

        return new JsonResponse([
            'message' => trans('klaviyo::app.admin.sync-started'),
            'data'    => $jobs->map(fn (SyncJob $job) => $this->presentJob($job))->all(),
        ]);
    }

    /**
     * The progress of the latest jobs, polled by the UI.
     */
    public function syncStatus(): JsonResponse
    {
        $this->authorizeKlaviyo();

        $jobs = SyncJob::query()
            ->whereIn('id', SyncJob::query()->selectRaw('max(id)')->groupBy('entity'))
            ->get();

        return new JsonResponse([
            'data' => $jobs->map(fn (SyncJob $job) => $this->presentJob($job))->values()->all(),
        ]);
    }

    /**
     * Requeue every failed outbox row of every connection.
     */
    public function retryFailed(): JsonResponse
    {
        $this->authorizeKlaviyo();

        $retried = 0;

        foreach (Connection::query()->get() as $connection) {
            $retried += $this->outboxItems->retryFailed((int) $connection->id);
        }

        return new JsonResponse([
            'message' => trans('klaviyo::app.admin.retried', ['count' => $retried]),
            'data'    => ['retried' => $retried],
        ]);
    }

    /**
     * The JSON:API-safe view of one connection; the key is never echoed.
     */
    protected function present(Connection $connection): array
    {
        return [
            'id'           => $connection->id,
            'channel_id'   => $connection->channel_id,
            'channel'      => $connection->channel?->code,
            'public_key'   => $connection->public_key,
            'status'       => $connection->status,
            'last_error'   => $connection->last_error,
            'connected_at' => $connection->connected_at?->toIso8601String(),
            'outbox'       => $this->outboxItems->stats((int) $connection->id),
        ];
    }

    /**
     * The sync progress view of one job.
     */
    protected function presentJob(SyncJob $job): array
    {
        return [
            'entity'    => $job->entity,
            'status'    => $job->status,
            'processed' => (int) $job->processed,
            'total'     => (int) $job->total,
        ];
    }

    /**
     * How many rows a sync job will walk.
     */
    protected function countEntity(Connection $connection, string $entity): int
    {
        return match ($entity) {
            'customers' => Customer::query()
                ->where('channel_id', $connection->channel_id)
                ->count(),
            'orders'    => Order::query()
                ->where('channel_id', $connection->channel_id)
                ->where('created_at', '>=', now()->subMonths(12))
                ->count(),
            'catalog' => DB::table('product_flat')
                ->where('channel', $connection->channel->code)
                ->where('status', 1)
                ->distinct()
                ->count('product_id'),
            default => 0,
        };
    }

    /**
     * Guard the whole controller behind the Klaviyo ACL key.
     */
    protected function authorizeKlaviyo(): void
    {
        if (! bouncer()->hasPermission('marketing.automation.klaviyo')) {
            abort(401, 'This action is unauthorized.');
        }
    }
}
