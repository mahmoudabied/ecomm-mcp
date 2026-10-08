<?php

return [
    'acl' => [
        'automation'   => 'Automation',
        'flows'        => 'Flows',
        'flows-manage' => 'Manage Flows',
        'reports'      => 'Reports',
        'settings'     => 'Providers & Settings',
        'klaviyo'      => 'Klaviyo',
    ],

    'menu' => [
        'automation' => 'Automation',
        'flows'      => 'Flows',
        'providers'  => 'Providers & Settings',
    ],

    'configuration' => [
        'title' => 'Marketing Automation',
        'info'  => 'Configure the automation engine, sending limits and routing.',

        'general' => [
            'title'    => 'General',
            'info'     => 'General automation settings.',
            'settings' => [
                'title'    => 'Settings',
                'info'     => 'Enable or disable the automation engine.',
                'enabled'  => 'Enable automation',
            ],
        ],

        'email' => [
            'title'  => 'Email',
            'info'   => 'Sender identity for automation emails.',
            'sender' => [
                'title'           => 'Sender',
                'info'            => 'Used as the From header of automation emails.',
                'sender-name'     => 'Sender name',
                'sender-address'  => 'Sender address',
            ],
        ],

        'sending' => [
            'title'  => 'Sending',
            'info'   => 'Quiet hours and frequency caps for marketing messages.',
            'limits' => [
                'title'                 => 'Limits',
                'info'                  => 'Applied to marketing messages only.',
                'quiet-hours-enabled'   => 'Enable quiet hours (SMS & WhatsApp)',
                'quiet-hours-start'     => 'Quiet hours start (HH:MM)',
                'quiet-hours-end'       => 'Quiet hours end (HH:MM)',
                'cap-count'             => 'Messages per window',
                'cap-window-hours'      => 'Frequency window (hours)',
            ],
        ],

        'abandoned-cart' => [
            'title'    => 'Abandoned Cart',
            'info'     => 'Cart recovery settings.',
            'settings' => [
                'title'              => 'Settings',
                'info'               => 'Validity of cart restore links.',
                'restore-link-days'  => 'Restore link validity (days)',
            ],
        ],

        'attribution' => [
            'title'    => 'Attribution',
            'info'     => 'How long clicks and deliveries count for revenue attribution.',
            'settings' => [
                'title'                  => 'Settings',
                'info'                   => 'Attribution windows.',
                'click-window-hours'     => 'Click window (hours)',
                'delivered-window-hours' => 'Delivery window (hours)',
            ],
        ],

        'retention' => [
            'title'    => 'Retention',
            'info'     => 'How long raw message data is kept.',
            'settings' => [
                'title'       => 'Settings',
                'info'        => 'Logs are purged after this period.',
                'log-months'  => 'Keep logs for (months)',
            ],
        ],

        'routing' => [
            'title'    => 'Routing',
            'info'     => 'Choose which platform runs each flow type.',
            'settings' => [
                'title'            => 'Settings',
                'info'             => 'Native runs inside this store, Klaviyo delegates to Klaviyo, Disabled turns the flow type off.',
                'abandoned-cart'   => 'Abandoned cart',
                'welcome'          => 'Welcome',
                'post-purchase'    => 'Post purchase',
                'win-back'         => 'Win back',
                'order-status'     => 'Order status',
                'back-in-stock'    => 'Back in stock',
            ],
        ],
    ],

    'providers' => [
        'test-message' => 'This is a test message from your store.',
    ],

    'health' => [
        'title'            => 'Setup Health',
        'queue'            => 'Queue worker',
        'queue-hint'       => 'Current driver: :driver. Set QUEUE_CONNECTION to database or redis and run the queue worker.',
        'scheduler'        => 'Scheduler',
        'scheduler-hint'   => 'Add the Laravel scheduler (schedule:work) so automation steps run every minute.',
        'scheduler-last'   => 'Last heartbeat: :time (runs every minute via automation:dispatch-due).',
        'scheduler-never'  => 'No heartbeat yet. The scheduler has not run automation:dispatch-due.',
        'mail'             => 'Mail configuration',
        'mail-hint'        => 'Set real SMTP credentials in .env; log/array drivers do not deliver to customers.',
        'ok'               => 'OK',
        'warning'          => 'Attention',
    ],

    'commands' => [
        'install' => [
            'migrated' => 'Migrated :count newsletter subscriptions into email consent.',
        ],
    ],
];
