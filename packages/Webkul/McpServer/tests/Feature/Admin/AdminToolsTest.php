<?php

use Webkul\McpServer\Mcp\Admin\Tools\GetSalesAnalytics;
use Webkul\McpServer\Mcp\Admin\Tools\HelloWorldTool;
use Webkul\McpServer\Mcp\Admin\Tools\ManageCustomers;
use Webkul\McpServer\Mcp\Admin\Tools\UpdateInventory;
use Webkul\User\Models\Admin;

it('admin hello world tool returns ok when enabled', function () {
    config(['mcp.diagnostic.hello_world_enabled' => true]);

    $admin = Admin::factory()->create();
    $channel = core()->getCurrentChannel();
    $this->resolveChannel($channel);

    $response = $this->actingAsAdmin($admin)
        ->tool(HelloWorldTool::class);

    $response->assertOk();
    $response->assertSee('ok');
});

it('admin hello world tool is not registered when disabled', function () {
    config(['mcp.diagnostic.hello_world_enabled' => false]);

    $admin = Admin::factory()->create();
    $channel = core()->getCurrentChannel();
    $this->resolveChannel($channel);

    $response = $this->actingAsAdmin($admin)
        ->tool(HelloWorldTool::class);

    $response->assertHasErrors(['not found']);
});

it('update inventory returns error for non-existent product', function () {
    $admin = Admin::factory()->create();
    $channel = core()->getCurrentChannel();
    $this->resolveChannel($channel);

    $response = $this->actingAsAdmin($admin)
        ->tool(UpdateInventory::class, [
            'sku' => 'NON-EXISTENT-SKU-12345',
            'qty' => 10,
        ]);

    $response->assertHasErrors();
});

it('update inventory returns unauthorized without auth', function () {
    $channel = core()->getCurrentChannel();
    $this->resolveChannel($channel);

    $response = $this->adminServer()
        ->tool(UpdateInventory::class, [
            'sku' => 'some-sku',
            'qty' => 10,
        ]);

    $response->assertHasErrors(['Unauthorized']);
});

it('get sales analytics returns data for valid date range', function () {
    $admin = Admin::factory()->create();
    $channel = core()->getCurrentChannel();
    $this->resolveChannel($channel);

    $response = $this->actingAsAdmin($admin)
        ->tool(GetSalesAnalytics::class, [
            'date_from' => now()->subMonth()->format('Y-m-d'),
            'date_to'   => now()->format('Y-m-d'),
        ]);

    $response->assertOk();
    $response->assertSee('total_orders');
});

it('get sales analytics validates date format', function () {
    $admin = Admin::factory()->create();
    $channel = core()->getCurrentChannel();
    $this->resolveChannel($channel);

    $response = $this->actingAsAdmin($admin)
        ->tool(GetSalesAnalytics::class, [
            'date_from' => 'invalid-date',
            'date_to'   => 'also-invalid',
        ]);

    $response->assertHasErrors(['YYYY-MM-DD']);
});

it('get sales analytics validates date_from before date_to', function () {
    $admin = Admin::factory()->create();
    $channel = core()->getCurrentChannel();
    $this->resolveChannel($channel);

    $response = $this->actingAsAdmin($admin)
        ->tool(GetSalesAnalytics::class, [
            'date_from' => '2025-12-31',
            'date_to'   => '2025-01-01',
        ]);

    $response->assertHasErrors(['date_from']);
});

it('manage customers returns error for non-existent customer', function () {
    $admin = Admin::factory()->create();
    $channel = core()->getCurrentChannel();
    $this->resolveChannel($channel);

    $response = $this->actingAsAdmin($admin)
        ->tool(ManageCustomers::class, [
            'customer_id' => 999999,
            'action'      => 'get_details',
        ]);

    $response->assertHasErrors(['not found']);
});

it('manage customers returns unauthorized without auth', function () {
    $channel = core()->getCurrentChannel();
    $this->resolveChannel($channel);

    $response = $this->adminServer()
        ->tool(ManageCustomers::class, [
            'customer_id' => 1,
            'action'      => 'get_details',
        ]);

    $response->assertHasErrors(['Unauthorized']);
});
