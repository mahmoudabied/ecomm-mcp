<?php

return [

    'tools' => [
        'shop' => [
            'search_products'     => true,
            'get_cart_details'    => true,
            'check_order_status'  => true,
        ],

        'admin' => [
            'update_inventory'    => true,
            'get_sales_analytics' => true,
            'manage_customers'    => true,
        ],
    ],

    'diagnostic' => [
        'hello_world_enabled' => env('MCP_HELLO_WORLD_ENABLED', env('APP_DEBUG', false)),
    ],

];
