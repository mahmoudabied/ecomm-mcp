<?php

namespace Webkul\McpServer\Tests;

use Laravel\Mcp\Server\Testing\PendingTestResponse;
use Tests\TestCase;
use Webkul\Core\Models\Channel;
use Webkul\Customer\Models\Customer;
use Webkul\McpServer\Mcp\Admin\AdminMcpServer;
use Webkul\McpServer\Mcp\Shop\ShopMcpServer;
use Webkul\User\Models\Admin;

abstract class McpTestCase extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'auth.guards.customer-jwt' => [
                'driver'   => 'jwt',
                'provider' => 'customers',
            ],
            'auth.guards.admin-jwt' => [
                'driver'   => 'jwt',
                'provider' => 'admins',
            ],
            'auth.providers.customers' => [
                'driver' => 'eloquent',
                'model'  => Customer::class,
            ],
            'auth.providers.admins' => [
                'driver' => 'eloquent',
                'model'  => Admin::class,
            ],
        ]);
    }

    protected function shopServer(): PendingTestResponse
    {
        return new PendingTestResponse($this->app, ShopMcpServer::class);
    }

    protected function adminServer(): PendingTestResponse
    {
        return new PendingTestResponse($this->app, AdminMcpServer::class);
    }

    protected function resolveChannel(Channel $channel): void
    {
        request()->attributes->set('mcp_channel', $channel);
    }

    protected function actingAsAdmin(Admin $admin): PendingTestResponse
    {
        $this->app['auth']->guard('admin-jwt')->setUser($admin);
        $this->app['auth']->guard('admin')->setUser($admin);
        $this->app['auth']->shouldUse('admin-jwt');

        return $this->adminServer();
    }
}
