<?php

namespace Webkul\McpServer\Mcp\Admin\Tools;

use Carbon\Carbon;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Laravel\Mcp\Request as McpRequest;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Webkul\Core\Repositories\ChannelRepository;
use Webkul\McpServer\Concerns\ResolvesChannel;
use Webkul\McpServer\Enums\McpErrorCode;
use Webkul\McpServer\Traits\FormatsResponse;
use Webkul\Sales\Repositories\OrderRepository;

#[Description('Get aggregated sales analytics for a date range. Requires analytics permission.')]
class GetSalesAnalytics extends Tool
{
    use FormatsResponse;
    use ResolvesChannel;

    public function __construct(
        protected OrderRepository $orderRepository,
        protected ChannelRepository $channelRepository,
    ) {}

    public function schema(JsonSchema $schema): array
    {
        return [
            'date_from' => $schema->string()->description('Start date (YYYY-MM-DD)')->required(),
            'date_to'   => $schema->string()->description('End date (YYYY-MM-DD)')->required(),
            'channel'   => $schema->string()->description('Channel code to scope analytics (defaults to all channels)'),
        ];
    }

    public function handle(McpRequest $request): Response
    {
        $admin = auth()->guard('admin-jwt')->user();
        if (! $admin) {
            return Response::error('Unauthorized', McpErrorCode::UNAUTHORIZED);
        }

        if (! bouncer()->hasPermission('mcp.admin.sales.analytics')) {
            return Response::error('Permission denied', McpErrorCode::PERMISSION_DENIED);
        }

        $dateFrom = $request->get('date_from');
        $dateTo = $request->get('date_to');

        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
            return Response::error('Dates must be in YYYY-MM-DD format', McpErrorCode::VALIDATION_ERROR);
        }

        if (Carbon::parse($dateFrom)->gt(Carbon::parse($dateTo))) {
            return Response::error('date_from must be before or equal to date_to', McpErrorCode::VALIDATION_ERROR);
        }

        $channelCode = $request->get('channel');

        $channelCodeValue = 'all';
        $channelId = null;

        if ($channelCode) {
            $channel = $this->channelRepository->findOneByField('code', $channelCode);

            if (! $channel) {
                return Response::error("Channel not found: {$channelCode}", McpErrorCode::CHANNEL_NOT_FOUND);
            }

            $channelCodeValue = $channel->code;
            $channelId = $channel->id;
        }

        $start = Carbon::parse($dateFrom)->startOfDay();
        $end = Carbon::parse($dateTo)->endOfDay();

        $orderTable = (new \Webkul\Sales\Models\Order)->getTable();
        $itemTable = (new \Webkul\Sales\Models\OrderItem)->getTable();

        $baseQuery = DB::table($orderTable)
            ->where("{$orderTable}.created_at", '>=', $start)
            ->where("{$orderTable}.created_at", '<=', $end)
            ->whereIn("{$orderTable}.status", ['completed', 'processing']);

        if ($channelId) {
            $baseQuery->where('channel_id', $channelId);
        }

        $totalOrders = (clone $baseQuery)->count();
        $totalRevenue = (float) (clone $baseQuery)->sum('base_grand_total');
        $averageOrderValue = $totalOrders > 0 ? $totalRevenue / $totalOrders : 0;

        $topProducts = (clone $baseQuery)
            ->join($itemTable, "{$orderTable}.id", '=', "{$itemTable}.order_id")
            ->whereNull("{$itemTable}.parent_id")
            ->selectRaw("{$itemTable}.sku, {$itemTable}.name, SUM({$itemTable}.qty_ordered) as total_qty, SUM({$itemTable}.base_total) as total_revenue")
            ->groupBy("{$itemTable}.sku", "{$itemTable}.name")
            ->orderByDesc('total_qty')
            ->limit(5)
            ->get()
            ->map(fn ($row) => [
                'sku'        => $row->sku,
                'name'       => $row->name ?? 'Unknown',
                'units_sold' => (int) $row->total_qty,
                'revenue'    => core()->formatPrice((float) $row->total_revenue),
            ])
            ->values()
            ->all();

        return $this->jsonResponse([
            'channel_code'        => $channelCodeValue,
            'period'              => [
                'from' => $dateFrom,
                'to'   => $dateTo,
            ],
            'total_orders'        => $totalOrders,
            'total_revenue'       => core()->formatPrice($totalRevenue),
            'average_order_value' => core()->formatPrice($averageOrderValue),
            'top_products'        => $topProducts,
        ]);
    }
}
