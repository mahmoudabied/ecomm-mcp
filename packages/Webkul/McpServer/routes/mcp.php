<?php

use Laravel\Mcp\Facades\Mcp;
use Webkul\McpServer\Mcp\Shop\ShopMcpServer;
use Webkul\McpServer\Mcp\Admin\AdminMcpServer;

Mcp::web('/api/mcp/shop', ShopMcpServer::class)
    ->middleware(['auth:customer-jwt', 'mcp.resolve-channel']);

Mcp::web('/api/mcp/admin', AdminMcpServer::class)
    ->middleware(['auth:admin-jwt', 'mcp.resolve-channel']);

Mcp::local('shop', ShopMcpServer::class);
Mcp::local('admin', AdminMcpServer::class);
