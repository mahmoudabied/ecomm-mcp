<?php

namespace Webkul\Klaviyo\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\DB;
use Spatie\Multitenancy\Jobs\NotTenantAware;
use Throwable;
use Webkul\Customer\Models\Customer;
use Webkul\Klaviyo\Models\SyncJob;
use Webkul\Klaviyo\Repositories\OutboxItemRepository;
use Webkul\Klaviyo\Services\PayloadMapper;
use Webkul\MarketingAutomation\Enums\ConsentStatus;
use Webkul\MarketingAutomation\Models\Consent;
use Webkul\MarketingAutomation\Models\Contact;
use Webkul\MarketingAutomation\Services\ContactResolver;
use Webkul\Product\Models\Product;
use Webkul\Sales\Models\Order;

class HistoricalSync implements NotTenantAware, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    /**
     * Rows loaded per pass.
     */
    protected const CHUNK = 100;

    /**
     * How far back the order history reaches.
     */
    protected const ORDER_LOOKBACK_MONTHS = 12;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $syncJobId,
    ) {
        $this->onQueue('klaviyo');
    }

    /**
     * Sync one chunk, then re-dispatch until the cursor reaches the end.
     */
    public function handle(PayloadMapper $mapper, ContactResolver $contactResolver, OutboxItemRepository $outboxItems): void
    {
        $job = SyncJob::query()->find($this->syncJobId);

        if ($job === null || in_array($job->status, ['completed', 'failed'], true)) {
            return;
        }

        SyncJob::query()->where('id', $job->id)->update([
            'status'     => 'running',
            'started_at' => $job->started_at ?? now(),
        ]);

        try {
            $finished = $this->sync($job, $mapper, $contactResolver, $outboxItems);

            $this->finish($job, $finished);
        } catch (Throwable $exception) {
            report($exception);

            SyncJob::query()->where('id', $job->id)->update([
                'status'      => 'failed',
                'last_error'  => substr($exception->getMessage(), 0, 255),
                'finished_at' => now(),
            ]);
        }
    }

    /**
     * Run one chunk of the job's entity.
     */
    protected function sync(SyncJob $job, PayloadMapper $mapper, ContactResolver $contactResolver, OutboxItemRepository $outboxItems): bool
    {
        return match ($job->entity) {
            'customers' => $this->syncCustomers($job, $mapper, $contactResolver, $outboxItems),
            'orders'    => $this->syncOrders($job, $mapper, $contactResolver, $outboxItems),
            'catalog'   => $this->syncCatalog($job, $mapper, $outboxItems),
            default     => true,
        };
    }

    /**
     * Queue profile imports with the store's consent state, so Klaviyo
     * receives the same opt-in picture the live events carry.
     */
    protected function syncCustomers(SyncJob $job, PayloadMapper $mapper, ContactResolver $contactResolver, OutboxItemRepository $outboxItems): bool
    {
        $rows = Customer::query()
            ->where('id', '>', $job->cursor_id)
            ->orderBy('id')
            ->limit(self::CHUNK)
            ->get();

        $channelCode = $job->connection->channel->code;

        foreach ($rows as $customer) {
            $contact = $contactResolver->forCustomer($customer, (int) $job->connection->channel_id);

            $outboxItems->writeOrIgnore($job->connection_id, 'profile', "{$channelCode}:profile:customer:{$customer->id}", [
                'data' => [
                    'type'       => 'profile',
                    'attributes' => $mapper->profile($contact),
                ],
            ]);

            foreach ($contact->consents as $consent) {
                [$uniqueId, $payload] = $this->subscriptionRow($mapper, $contact, $consent, $channelCode);

                $outboxItems->writeOrIgnore($job->connection_id, 'subscription', $uniqueId, $payload);
            }

            $job->cursor_id = $customer->id;
        }

        return $this->advance($job, $rows->count());
    }

    /**
     * The stable id and payload of the subscription row that mirrors one
     * store consent into Klaviyo; granted pushes a subscribe, anything else
     * an unsubscribe.
     *
     * @return array{0: string, 1: array<string, mixed>}
     */
    protected function subscriptionRow(PayloadMapper $mapper, Contact $contact, Consent $consent, string $channelCode): array
    {
        $subscribing = $consent->status === ConsentStatus::Granted;

        $uniqueId = substr("{$channelCode}:subscription:sync:{$contact->id}:{$consent->channel->value}", 0, 191);

        return [$uniqueId, [
            'channel'          => $consent->channel,
            'subscribed'       => $subscribing,
            'profile'          => $mapper->profile($contact),
            'event_attributes' => [
                'metric'    => ['data' => ['type' => 'metric', 'attributes' => ['name' => $subscribing ? 'Subscribed to Marketing' : 'Unsubscribed from Marketing']]],
                'profile'   => ['data' => ['type' => 'profile', 'attributes' => $mapper->profile($contact)]],
                'unique_id' => $uniqueId,
                'time'      => now()->toIso8601String(),
            ],
        ]];
    }

    /**
     * Queue the placed-order events of the last twelve months.
     */
    protected function syncOrders(SyncJob $job, PayloadMapper $mapper, ContactResolver $contactResolver, OutboxItemRepository $outboxItems): bool
    {
        $rows = Order::query()
            ->where('channel_id', $job->connection->channel_id)
            ->where('created_at', '>=', now()->subMonths(self::ORDER_LOOKBACK_MONTHS))
            ->where('id', '>', $job->cursor_id)
            ->with('items')
            ->orderBy('id')
            ->limit(self::CHUNK)
            ->get();

        $channelCode = $job->connection->channel->code;

        foreach ($rows as $order) {
            $attributes = $mapper->profile($contactResolver->resolve(
                channelId: (int) $order->channel_id,
                customerId: $order->customer_id,
                email: $order->customer_email,
                phone: $order->billing_address?->phone,
            ));

            $outboxItems->writeOrIgnore($job->connection_id, 'profile', "{$channelCode}:profile:{$order->id}", [
                'data' => [
                    'type'       => 'profile',
                    'attributes' => $attributes,
                ],
            ]);

            $placed = $mapper->placedOrder($order);

            $outboxItems->writeOrIgnore(
                $job->connection_id,
                'event',
                substr((string) $placed['data']['attributes']['unique_id'], 0, 191),
                $placed
            );

            foreach ($order->items as $item) {
                $payload = $mapper->orderedProduct($order, $item);

                $outboxItems->writeOrIgnore(
                    $job->connection_id,
                    'event',
                    substr((string) $payload['data']['attributes']['unique_id'], 0, 191),
                    $payload
                );
            }

            $job->cursor_id = $order->id;
        }

        return $this->advance($job, $rows->count());
    }

    /**
     * Queue catalog items of the channel's active products.
     */
    protected function syncCatalog(SyncJob $job, PayloadMapper $mapper, OutboxItemRepository $outboxItems): bool
    {
        $productIds = DB::table('product_flat')
            ->where('channel', $job->connection->channel->code)
            ->where('status', 1)
            ->where('product_id', '>', $job->cursor_id)
            ->distinct()
            ->orderBy('product_id')
            ->limit(self::CHUNK)
            ->pluck('product_id');

        $products = Product::query()->whereIn('id', $productIds)->get()->keyBy('id');

        foreach ($productIds as $productId) {
            $product = $products->get($productId);

            if ($product === null) {
                continue;
            }

            $item = $mapper->catalogItem($product, $job->connection->channel);

            $outboxItems->writeOrIgnore($job->connection_id, 'catalog_item', (string) $item['data']['id'], $item);

            $job->cursor_id = $productId;
        }

        return $this->advance($job, $productIds->count());
    }

    /**
     * Track progress; a chunk smaller than the limit means the table is done.
     */
    protected function advance(SyncJob $job, int $count): bool
    {
        SyncJob::query()->where('id', $job->id)->increment('processed', $count);

        return $count < self::CHUNK;
    }

    /**
     * Close the job, or queue the next chunk from the cursor.
     */
    protected function finish(SyncJob $job, bool $finished): void
    {
        if (! $finished) {
            SyncJob::query()->where('id', $job->id)->update([
                'cursor_id' => $job->cursor_id,
            ]);

            self::dispatch($job->id);

            return;
        }

        SyncJob::query()->where('id', $job->id)->update([
            'status'      => 'completed',
            'cursor_id'   => $job->cursor_id,
            'finished_at' => now(),
        ]);
    }
}
