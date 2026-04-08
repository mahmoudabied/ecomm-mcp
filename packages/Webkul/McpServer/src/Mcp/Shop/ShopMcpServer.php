<?php

namespace Webkul\McpServer\Mcp\Shop;

use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;
use Webkul\McpServer\Mcp\Shop\Tools\CheckOrderStatus;
use Webkul\McpServer\Mcp\Shop\Tools\GetCartDetails;
use Webkul\McpServer\Mcp\Shop\Tools\HelloWorldTool;
use Webkul\McpServer\Mcp\Shop\Tools\SearchProducts;

#[Name('Bagisto Shop')]
#[Version('1.0.0')]
#[Instructions('Interact with the Bagisto storefront: search products, manage cart, check orders.')]
class ShopMcpServer extends Server
{
    protected array $tools = [
        HelloWorldTool::class,
        SearchProducts::class,
        GetCartDetails::class,
        CheckOrderStatus::class,
    ];

    protected function boot(): void
    {
        $this->tools = array_values(array_filter($this->tools, function (string $toolClass): bool {
            return match ($toolClass) {
                HelloWorldTool::class => (bool) config('mcp.diagnostic.hello_world_enabled', false),
                SearchProducts::class => (bool) config('mcp.tools.shop.search_products', true),
                GetCartDetails::class => (bool) config('mcp.tools.shop.get_cart_details', true),
                CheckOrderStatus::class => (bool) config('mcp.tools.shop.check_order_status', true),
                default => true,
            };
        }));
    }
}
