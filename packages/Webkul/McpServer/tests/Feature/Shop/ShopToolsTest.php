<?php

use Webkul\Customer\Models\Customer;
use Webkul\McpServer\Mcp\Shop\Tools\CheckOrderStatus;
use Webkul\McpServer\Mcp\Shop\Tools\GetCartDetails;
use Webkul\McpServer\Mcp\Shop\Tools\HelloWorldTool;
use Webkul\McpServer\Mcp\Shop\Tools\SearchProducts;

it('hello world tool returns ok when enabled', function () {
    config(['mcp.diagnostic.hello_world_enabled' => true]);

    $customer = Customer::factory()->create();
    $channel = core()->getCurrentChannel();
    $this->resolveChannel($channel);

    $response = $this->shopServer()
        ->actingAs($customer, 'customer-jwt')
        ->tool(HelloWorldTool::class);

    $response->assertOk();
    $response->assertSee('ok');
});

it('hello world tool is not registered when disabled', function () {
    config(['mcp.diagnostic.hello_world_enabled' => false]);

    $customer = Customer::factory()->create();
    $channel = core()->getCurrentChannel();
    $this->resolveChannel($channel);

    $response = $this->shopServer()
        ->actingAs($customer, 'customer-jwt')
        ->tool(HelloWorldTool::class);

    $response->assertHasErrors(['not found']);
});

it('search products returns results', function () {
    $customer = Customer::factory()->create();
    $channel = core()->getCurrentChannel();
    $this->resolveChannel($channel);

    $response = $this->shopServer()
        ->actingAs($customer, 'customer-jwt')
        ->tool(SearchProducts::class, [
            'query' => 'test',
        ]);

    $response->assertOk();
    $response->assertSee('products');
});

it('search products returns channel code', function () {
    $customer = Customer::factory()->create();
    $channel = core()->getCurrentChannel();
    $this->resolveChannel($channel);

    $response = $this->shopServer()
        ->actingAs($customer, 'customer-jwt')
        ->tool(SearchProducts::class, [
            'query' => '',
        ]);

    $response->assertOk();
    $response->assertSee($channel->code);
});

it('get cart details returns empty cart when no items', function () {
    $customer = Customer::factory()->create();
    $channel = core()->getCurrentChannel();
    $this->resolveChannel($channel);

    $response = $this->shopServer()
        ->actingAs($customer, 'customer-jwt')
        ->tool(GetCartDetails::class);

    $response->assertOk();
    $response->assertSee('items_count');
});

it('check order status returns error for non-existent order', function () {
    $customer = Customer::factory()->create();
    $channel = core()->getCurrentChannel();
    $this->resolveChannel($channel);

    $response = $this->shopServer()
        ->actingAs($customer, 'customer-jwt')
        ->tool(CheckOrderStatus::class, [
            'order_id' => '999999999',
        ]);

    $response->assertHasErrors(['not found']);
});

it('check order status returns error for unauthenticated user', function () {
    $channel = core()->getCurrentChannel();
    $this->resolveChannel($channel);

    $response = $this->shopServer()
        ->tool(CheckOrderStatus::class, [
            'order_id' => '100000001',
        ]);

    $response->assertHasErrors(['Unauthorized']);
});
