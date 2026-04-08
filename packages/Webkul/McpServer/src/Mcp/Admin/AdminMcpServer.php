<?php

namespace Webkul\McpServer\Mcp\Admin;

use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;
use Webkul\McpServer\Mcp\Admin\Tools\GetSalesAnalytics;
use Webkul\McpServer\Mcp\Admin\Tools\HelloWorldTool;
use Webkul\McpServer\Mcp\Admin\Tools\ManageCustomers;
use Webkul\McpServer\Mcp\Admin\Tools\UpdateInventory;

#[Name('Bagisto Admin')]
#[Version('1.0.0')]
#[Instructions('Manage the Bagisto store: update inventory, view sales analytics, manage customers.')]
class AdminMcpServer extends Server
{
    protected array $tools = [
        HelloWorldTool::class,
        UpdateInventory::class,
        GetSalesAnalytics::class,
        ManageCustomers::class,
    ];

    protected function boot(): void
    {
        $this->tools = array_values(array_filter($this->tools, function (string $toolClass): bool {
            return match ($toolClass) {
                HelloWorldTool::class    => (bool) config('mcp.diagnostic.hello_world_enabled', false),
                UpdateInventory::class   => (bool) config('mcp.tools.admin.update_inventory', true),
                GetSalesAnalytics::class => (bool) config('mcp.tools.admin.get_sales_analytics', true),
                ManageCustomers::class   => (bool) config('mcp.tools.admin.manage_customers', true),
                default => true,
            };
        }));
    }
}
