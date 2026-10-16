<?php

return [
    /**
     * Marketing Automation.
     */
    [
        'key'  => 'marketing_automation',
        'name' => 'marketing_automation::app.configuration.title',
        'info' => 'marketing_automation::app.configuration.info',
        'sort' => 7,
    ], [
        'key'  => 'marketing_automation.general',
        'name' => 'marketing_automation::app.configuration.general.title',
        'info' => 'marketing_automation::app.configuration.general.info',
        'icon' => 'settings/store.svg',
        'sort' => 1,
    ], [
        'key'    => 'marketing_automation.general.settings',
        'name'   => 'marketing_automation::app.configuration.general.settings.title',
        'info'   => 'marketing_automation::app.configuration.general.settings.info',
        'sort'   => 1,
        'fields' => [
            [
                'name'          => 'enabled',
                'title'         => 'marketing_automation::app.configuration.general.settings.enabled',
                'type'          => 'boolean',
                'default'       => 1,
                'channel_based' => true,
            ],
        ],
    ], [
        'key'  => 'marketing_automation.email',
        'name' => 'marketing_automation::app.configuration.email.title',
        'info' => 'marketing_automation::app.configuration.email.info',
        'icon' => 'settings/store.svg',
        'sort' => 2,
    ], [
        'key'    => 'marketing_automation.email.sender',
        'name'   => 'marketing_automation::app.configuration.email.sender.title',
        'info'   => 'marketing_automation::app.configuration.email.sender.info',
        'sort'   => 1,
        'fields' => [
            [
                'name'          => 'sender_name',
                'title'         => 'marketing_automation::app.configuration.email.sender.sender-name',
                'type'          => 'text',
                'channel_based' => true,
            ], [
                'name'          => 'sender_address',
                'title'         => 'marketing_automation::app.configuration.email.sender.sender-address',
                'type'          => 'text',
                'validation'    => 'email',
                'channel_based' => true,
            ],
        ],
    ], [
        'key'  => 'marketing_automation.sending',
        'name' => 'marketing_automation::app.configuration.sending.title',
        'info' => 'marketing_automation::app.configuration.sending.info',
        'icon' => 'settings/store.svg',
        'sort' => 3,
    ], [
        'key'    => 'marketing_automation.sending.limits',
        'name'   => 'marketing_automation::app.configuration.sending.limits.title',
        'info'   => 'marketing_automation::app.configuration.sending.limits.info',
        'sort'   => 1,
        'fields' => [
            [
                'name'          => 'quiet_hours_enabled',
                'title'         => 'marketing_automation::app.configuration.sending.limits.quiet-hours-enabled',
                'type'          => 'boolean',
                'default'       => 0,
                'channel_based' => true,
            ], [
                'name'          => 'quiet_hours_start',
                'title'         => 'marketing_automation::app.configuration.sending.limits.quiet-hours-start',
                'type'          => 'text',
                'default'       => '22:00',
                'channel_based' => true,
            ], [
                'name'          => 'quiet_hours_end',
                'title'         => 'marketing_automation::app.configuration.sending.limits.quiet-hours-end',
                'type'          => 'text',
                'default'       => '09:00',
                'channel_based' => true,
            ], [
                'name'          => 'cap_count',
                'title'         => 'marketing_automation::app.configuration.sending.limits.cap-count',
                'type'          => 'number',
                'default'       => 2,
                'channel_based' => true,
            ], [
                'name'          => 'cap_window_hours',
                'title'         => 'marketing_automation::app.configuration.sending.limits.cap-window-hours',
                'type'          => 'number',
                'default'       => 24,
                'channel_based' => true,
            ],
        ],
    ], [
        'key'  => 'marketing_automation.abandoned_cart',
        'name' => 'marketing_automation::app.configuration.abandoned-cart.title',
        'info' => 'marketing_automation::app.configuration.abandoned-cart.info',
        'icon' => 'settings/store.svg',
        'sort' => 4,
    ], [
        'key'    => 'marketing_automation.abandoned_cart.settings',
        'name'   => 'marketing_automation::app.configuration.abandoned-cart.settings.title',
        'info'   => 'marketing_automation::app.configuration.abandoned-cart.settings.info',
        'sort'   => 1,
        'fields' => [
            [
                'name'          => 'restore_link_days',
                'title'         => 'marketing_automation::app.configuration.abandoned-cart.settings.restore-link-days',
                'type'          => 'number',
                'default'       => 7,
                'channel_based' => true,
            ],
        ],
    ], [
        'key'  => 'marketing_automation.attribution',
        'name' => 'marketing_automation::app.configuration.attribution.title',
        'info' => 'marketing_automation::app.configuration.attribution.info',
        'icon' => 'settings/store.svg',
        'sort' => 5,
    ], [
        'key'    => 'marketing_automation.attribution.settings',
        'name'   => 'marketing_automation::app.configuration.attribution.settings.title',
        'info'   => 'marketing_automation::app.configuration.attribution.settings.info',
        'sort'   => 1,
        'fields' => [
            [
                'name'          => 'click_window_hours',
                'title'         => 'marketing_automation::app.configuration.attribution.settings.click-window-hours',
                'type'          => 'number',
                'default'       => 120,
                'channel_based' => true,
            ], [
                'name'          => 'delivered_window_hours',
                'title'         => 'marketing_automation::app.configuration.attribution.settings.delivered-window-hours',
                'type'          => 'number',
                'default'       => 24,
                'channel_based' => true,
            ],
        ],
    ], [
        'key'  => 'marketing_automation.retention',
        'name' => 'marketing_automation::app.configuration.retention.title',
        'info' => 'marketing_automation::app.configuration.retention.info',
        'icon' => 'settings/store.svg',
        'sort' => 6,
    ], [
        'key'    => 'marketing_automation.retention.settings',
        'name'   => 'marketing_automation::app.configuration.retention.settings.title',
        'info'   => 'marketing_automation::app.configuration.retention.settings.info',
        'sort'   => 1,
        'fields' => [
            [
                'name'          => 'log_months',
                'title'         => 'marketing_automation::app.configuration.retention.settings.log-months',
                'type'          => 'number',
                'default'       => 12,
                'channel_based' => false,
            ],
        ],
    ], [
        'key'  => 'marketing_automation.routing',
        'name' => 'marketing_automation::app.configuration.routing.title',
        'info' => 'marketing_automation::app.configuration.routing.info',
        'icon' => 'settings/store.svg',
        'sort' => 7,
    ], [
        'key'    => 'marketing_automation.routing.settings',
        'name'   => 'marketing_automation::app.configuration.routing.settings.title',
        'info'   => 'marketing_automation::app.configuration.routing.settings.info',
        'sort'   => 1,
        'fields' => [
            [
                'name'          => 'abandoned_cart',
                'title'         => 'marketing_automation::app.configuration.routing.settings.abandoned-cart',
                'type'          => 'select',
                'default'       => 'native',
                'options'       => [
                    ['title' => 'Native', 'value' => 'native'],
                    ['title' => 'Klaviyo', 'value' => 'klaviyo'],
                    ['title' => 'Disabled', 'value' => 'disabled'],
                ],
                'channel_based' => true,
            ], [
                'name'          => 'welcome',
                'title'         => 'marketing_automation::app.configuration.routing.settings.welcome',
                'type'          => 'select',
                'default'       => 'native',
                'options'       => [
                    ['title' => 'Native', 'value' => 'native'],
                    ['title' => 'Klaviyo', 'value' => 'klaviyo'],
                    ['title' => 'Disabled', 'value' => 'disabled'],
                ],
                'channel_based' => true,
            ], [
                'name'          => 'post_purchase',
                'title'         => 'marketing_automation::app.configuration.routing.settings.post-purchase',
                'type'          => 'select',
                'default'       => 'native',
                'options'       => [
                    ['title' => 'Native', 'value' => 'native'],
                    ['title' => 'Klaviyo', 'value' => 'klaviyo'],
                    ['title' => 'Disabled', 'value' => 'disabled'],
                ],
                'channel_based' => true,
            ], [
                'name'          => 'win_back',
                'title'         => 'marketing_automation::app.configuration.routing.settings.win-back',
                'type'          => 'select',
                'default'       => 'native',
                'options'       => [
                    ['title' => 'Native', 'value' => 'native'],
                    ['title' => 'Klaviyo', 'value' => 'klaviyo'],
                    ['title' => 'Disabled', 'value' => 'disabled'],
                ],
                'channel_based' => true,
            ], [
                'name'          => 'order_status',
                'title'         => 'marketing_automation::app.configuration.routing.settings.order-status',
                'type'          => 'select',
                'default'       => 'native',
                'options'       => [
                    ['title' => 'Native', 'value' => 'native'],
                    ['title' => 'Klaviyo', 'value' => 'klaviyo'],
                    ['title' => 'Disabled', 'value' => 'disabled'],
                ],
                'channel_based' => true,
            ], [
                'name'          => 'back_in_stock',
                'title'         => 'marketing_automation::app.configuration.routing.settings.back-in-stock',
                'type'          => 'select',
                'default'       => 'native',
                'options'       => [
                    ['title' => 'Native', 'value' => 'native'],
                    ['title' => 'Klaviyo', 'value' => 'klaviyo'],
                    ['title' => 'Disabled', 'value' => 'disabled'],
                ],
                'channel_based' => true,
            ],
        ],
    ],
];
