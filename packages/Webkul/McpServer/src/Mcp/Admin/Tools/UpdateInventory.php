<?php

namespace Webkul\McpServer\Mcp\Admin\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Laravel\Mcp\Request as McpRequest;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Webkul\Core\Repositories\ChannelRepository;
use Webkul\Inventory\Repositories\InventorySourceRepository;
use Webkul\McpServer\Concerns\ResolvesChannel;
use Webkul\McpServer\Enums\McpErrorCode;
use Webkul\McpServer\Traits\FormatsResponse;
use Webkul\Product\Repositories\ProductInventoryRepository;
use Webkul\Product\Repositories\ProductRepository;

#[Description('Update stock quantity for a product SKU. Requires inventory update permission.')]
class UpdateInventory extends Tool
{
    use FormatsResponse;
    use ResolvesChannel;

    public function __construct(
        protected ProductRepository $productRepository,
        protected ProductInventoryRepository $productInventoryRepository,
        protected InventorySourceRepository $inventorySourceRepository,
        protected ChannelRepository $channelRepository,
    ) {}

    public function schema(JsonSchema $schema): array
    {
        return [
            'sku'              => $schema->string()->description('Product SKU to update')->required(),
            'qty'              => $schema->integer()->description('New quantity (>= 0)')->required()->min(0),
            'inventory_source' => $schema->string()->description('Inventory source code (defaults to default source)'),
            'channel'          => $schema->string()->description('Channel code (defaults to current channel)'),
        ];
    }

    public function handle(McpRequest $request): Response
    {
        $admin = auth()->guard('admin-jwt')->user();
        if (! $admin) {
            return Response::error('Unauthorized', McpErrorCode::UNAUTHORIZED);
        }

        if (! bouncer()->hasPermission('mcp.admin.inventory.update')) {
            return Response::error('Permission denied', McpErrorCode::PERMISSION_DENIED);
        }

        $channelCode = $request->get('channel');

        if ($channelCode) {
            $channel = $this->channelRepository->findOneByField('code', $channelCode);

            if (! $channel) {
                return Response::error("Channel not found: {$channelCode}", McpErrorCode::CHANNEL_NOT_FOUND);
            }
        } else {
            $channel = $this->getChannel();
        }

        $sku = $request->get('sku');
        $product = $this->productRepository->findOneByField('sku', $sku);

        if (! $product) {
            return Response::error("Product not found for SKU: {$sku}", McpErrorCode::NOT_FOUND);
        }

        $qty = (int) $request->get('qty', 0);

        if ($qty < 0) {
            return Response::error('Quantity must be >= 0', McpErrorCode::VALIDATION_ERROR);
        }

        $sourceCode = $request->get('inventory_source');

        if ($sourceCode) {
            $inventorySource = $this->inventorySourceRepository
                ->findOneByField('code', $sourceCode);

            if (! $inventorySource) {
                return Response::error("Inventory source '{$sourceCode}' not found.", McpErrorCode::NOT_FOUND);
            }
        } else {
            $inventorySource = $channel->inventory_sources->first();
        }

        if (! $inventorySource) {
            return Response::error('No inventory source found', McpErrorCode::NOT_FOUND);
        }

        $previousInventory = $product->inventories()
            ->where('inventory_source_id', $inventorySource->id)
            ->first();

        $previousQty = $previousInventory ? (int) $previousInventory->qty : 0;

        DB::transaction(fn () => $this->productInventoryRepository->updateOrCreate(
            [
                'product_id'          => $product->id,
                'inventory_source_id' => $inventorySource->id,
            ],
            [
                'qty' => $qty,
            ]
        ));

        Log::info('[MCP] UpdateInventory', [
            'admin_id'           => $admin->id,
            'product_id'         => $product->id,
            'sku'                => $product->sku,
            'previous_qty'       => $previousQty,
            'new_qty'            => $qty,
            'inventory_source'   => $inventorySource->name,
            'channel_code'       => $channel->code,
        ]);

        return $this->jsonResponse([
            'channel_code'     => $channel->code,
            'sku'              => $product->sku,
            'inventory_source' => $inventorySource->name,
            'previous_qty'     => $previousQty,
            'new_qty'          => $qty,
            'updated_at'       => now()->toIso8601String(),
        ]);
    }
}
