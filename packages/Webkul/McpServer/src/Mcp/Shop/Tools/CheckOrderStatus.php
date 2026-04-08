<?php

namespace Webkul\McpServer\Mcp\Shop\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request as McpRequest;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Webkul\McpServer\Concerns\ResolvesChannel;
use Webkul\McpServer\Enums\McpErrorCode;
use Webkul\McpServer\Traits\FormatsResponse;
use Webkul\Sales\Repositories\OrderRepository;

#[Description('Check the status and tracking for an order belonging to the authenticated customer.')]
class CheckOrderStatus extends Tool
{
    use FormatsResponse;
    use ResolvesChannel;

    public function __construct(
        protected OrderRepository $orderRepository,
    ) {}

    public function schema(JsonSchema $schema): array
    {
        return [
            'order_id' => $schema->string()->description('Order increment ID (e.g. 100000001)')->required(),
        ];
    }

    public function handle(McpRequest $request): Response
    {
        $channel = $this->getChannel();

        $customerId = auth()->guard('customer-jwt')->id();

        if (! $customerId) {
            return Response::error('Unauthorized', McpErrorCode::UNAUTHORIZED);
        }

        $incrementId = $request->get('order_id');

        $order = $this->orderRepository->findOneByField('increment_id', $incrementId);

        if (! $order || $order->customer_id !== $customerId) {
            return Response::error('Order not found.', McpErrorCode::NOT_FOUND);
        }

        return $this->jsonResponse([
            'channel_code' => $channel->code,
            'increment_id' => $order->increment_id,
            'status'       => $order->status,
            'grand_total'  => core()->formatPrice($order->grand_total),
            'items'        => $order->items->map(fn ($item) => [
                'sku'      => $item->sku,
                'name'     => $item->name,
                'quantity' => $item->qty_ordered,
            ])->values()->all(),
            'shipments' => $order->shipments->map(fn ($shipment) => [
                'status'   => $shipment->status,
                'tracking' => $shipment->tracks->map(fn ($track) => [
                    'carrier' => $track->carrier_code,
                    'number'  => $track->track_number,
                ])->values()->all(),
            ])->values()->all(),
        ]);
    }
}
