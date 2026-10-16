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

        'index' => [
            'title'      => 'Messaging Providers',
            'create-btn' => 'Create provider',
            'edit-btn'   => 'Edit provider',
            'health'     => 'Health',
        ],

        'form' => [
            'name'              => 'Name',
            'channel'           => 'Message channel',
            'driver'            => 'Driver',
            'sender'            => 'Sender',
            'save'              => 'Save',
            'webhook-url'       => 'Webhook URL',
            'webhook-url-info'  => 'Paste this URL into the provider dashboard as the status / inbound callback.',
            'verify-token'      => 'Verify token',
            'verify-token-info' => 'Use this as the Verify Token when subscribing the webhook in the Meta app dashboard.',
            'copy'              => 'Copy',
            'copied'            => 'Copied to clipboard.',
        ],

        'datagrid' => [
            'id'           => 'ID',
            'name'         => 'Name',
            'channel'      => 'Channel',
            'driver'       => 'Driver',
            'sender'       => 'Sender',
            'status'       => 'Status',
            'last-tested'  => 'Last tested',
            'edit'         => 'Edit',
            'delete'       => 'Delete',
            'test'         => 'Test connection',
            'sync'         => 'Sync templates',
        ],

        'channels' => [
            'sms'      => 'SMS',
            'whatsapp' => 'WhatsApp',
        ],

        'test' => [
            'title'     => 'Test connection',
            'recipient' => 'Recipient phone number',
            'send'      => 'Send test message',
        ],

        'statuses' => [
            'untested'  => 'Untested',
            'connected' => 'Connected',
            'error'     => 'Error',
        ],

        'test-prompt'             => 'Recipient phone number for the test message:',
        'test-success'            => 'Connection works — the test message was accepted.',
        'test-failed'             => 'The provider rejected the test message.',
        'invalid-recipient'       => 'Please enter a valid phone number.',
        'templates-synced'        => 'Synced :count approved templates.',
        'templates-sync-failed'   => 'Template sync failed — check the credentials and try again.',
        'templates-not-supported' => 'This connection does not support WhatsApp templates.',
        'sync-templates-first'    => 'Sync WhatsApp templates first, then test again.',
        'template-not-approved'   => 'The template ":name" is not approved for this connection.',
        'driver-channel-mismatch' => 'The selected driver does not support this message channel.',
        'driver-immutable'        => 'The driver and message channel cannot be changed after creation.',
        'created'                 => 'Provider created.',
        'updated'                 => 'Provider updated.',
        'saved'                   => 'Provider saved.',
        'deleted'                 => 'Provider deleted.',
        'delete-confirm'          => 'Delete this provider connection?',
        'delete-blocked'          => 'This connection is the default for a sales channel that has an active flow. Choose another default first.',
        'defaults'                => [
            'title'   => 'Defaults per sales channel',
            'channel' => 'Sales channel',
            'save'    => 'Save defaults',
            'none'    => '—',
        ],
        'defaults-channel-mismatch' => 'The selected connection does not serve this message channel.',
        'defaults-updated'          => 'Default saved.',
    ],

    'emails' => [
        'unsubscribe' => 'Unsubscribe from these emails',

        'blocks' => [
            'product-grid-title' => 'You may also like',
        ],
    ],

    'reports' => [
        'title'        => 'Flow report',
        'from'         => 'From',
        'to'           => 'To',
        'channel'      => 'Sales channel',
        'all-channels' => 'All sales channels',
        'enrolled'     => 'Enrolled',
        'sent'         => 'Sent',
        'delivered'    => 'Delivered',
        'open-rate'    => 'Open rate',
        'click-rate'   => 'Click rate',
        'converted'    => 'Converted',
        'revenue'      => 'Revenue',
        'step'         => 'Step',
        'skipped'      => 'Skipped',
        'failed'       => 'Failed',
        'type'         => 'Type',
        'removed'      => 'Removed step',
        'enrolments'   => 'Enrolments',
        'journey'      => 'Journey',

        'enrolments-grid' => [
            'id'           => 'ID',
            'email'        => 'Email',
            'phone'        => 'Phone',
            'status'       => 'Status',
            'current-step' => 'Current step',
            'entered'      => 'Entered',
        ],
    ],

    'shop' => [
        'restore' => [
            'expired' => 'This cart recovery link has expired. Please review your cart directly.',
        ],

        'contact-capture' => [
            'email'    => 'Email me offers and updates',
            'sms'      => 'Send me SMS offers',
            'whatsapp' => 'Send me WhatsApp messages',
        ],

        'preferences' => [
            'title'    => 'Marketing preferences',
            'email'    => 'Email me offers and updates',
            'sms'      => 'Send me SMS offers',
            'whatsapp' => 'Send me WhatsApp messages',
            'save'     => 'Save preferences',
            'saved'    => 'Your marketing preferences were saved.',
            'link'     => 'Marketing preferences',
            'export'   => 'Download my marketing data',
        ],

        'unsubscribe' => [
            'title'        => 'Unsubscribe',
            'confirm-text' => 'Stop receiving marketing :channel messages from us?',
            'confirm'      => 'Unsubscribe',
            'done'         => 'You will no longer receive marketing :channel messages from us.',
            'channels'     => [
                'email'    => 'email',
                'sms'      => 'SMS',
                'whatsapp' => 'WhatsApp',
            ],
        ],

        'back-in-stock' => [
            'title'        => 'Notify me when available',
            'hint'         => 'Leave your contact details and we will message you the moment the product is back.',
            'email'        => 'Email address',
            'phone'        => 'Phone number',
            'via-email'    => 'Notify me by email',
            'via-sms'      => 'Notify me by SMS',
            'via-whatsapp' => 'Notify me on WhatsApp',
            'submit'       => 'Notify me',
            'created'      => 'You are on the list — we will contact you when it is back.',
        ],
    ],

    'flows' => [
        'index' => [
            'title'      => 'Automation Flows',
            'create-btn' => 'Create flow',
            'edit-btn'   => 'Edit flow',
            'templates'  => 'Start from a template',
            'blank'      => 'Blank flow',
        ],

        'form' => [
            'name'                 => 'Name',
            'trigger'              => 'Trigger',
            'type'                 => 'Type',
            'channels'             => 'Sales channels',
            'reentry-window'       => 'Re-entry window (hours)',
            'exit-goal'            => 'Exit when an order is placed',
            'save'                 => 'Save',
            'activate'             => 'Activate',
            'pause'                => 'Pause',
            'resume'               => 'Resume',
            'duplicate'            => 'Duplicate',
            'delete'               => 'Delete',
            'send-test'            => 'Send test',
            'send-test-recipient'  => 'Recipient (email or phone)',
            'send-test-locale'     => 'Locale',
            'send-test-sample'     => 'Sample data',
            'send-test-customer'   => 'Real customer',
            'send-test-send'       => 'Send',
            'add-step'             => 'Add step',
            'yes-branch'           => 'Yes',
            'no-branch'            => 'No',
        ],

        'steps' => [
            'wait'          => 'Wait',
            'condition'     => 'Condition',
            'send_email'    => 'Send email',
            'send_sms'      => 'Send SMS',
            'send_whatsapp' => 'Send WhatsApp',
            'exit'          => 'Exit',
            'duration'      => 'Duration (minutes)',
            'until-time'    => 'Until time (HH:MM)',
            'until-weekday' => 'Until weekday',
            'subject'       => 'Subject',
            'body'          => 'Body',
            'template'      => 'WhatsApp template',
            'variables'     => 'Variables',
            'fallback'      => 'Fallback channel',
            'fallback-body' => 'Fallback content',
            'validity'      => 'Validity (hours)',
            'field'         => 'Field',
            'operator'      => 'Operator',
            'value'         => 'Value',
            'match'         => 'Match',
            'all'           => 'All conditions',
            'any'           => 'Any condition',
        ],

        'editor' => [
            'blocks'          => 'Blocks',
            'copy-default'    => 'Copy from default locale',
            'insert-variable' => 'Insert variable',
            'pick-block'      => 'Select a block to edit its properties.',
            'preview'         => 'Refresh preview',
            'previewing'      => 'Loading preview…',
            'move-up'         => 'Move up',
            'move-down'       => 'Move down',
            'remove'          => 'Remove',

            'blocks-type' => [
                'header'        => 'Header',
                'text'          => 'Text',
                'image'         => 'Image',
                'button'        => 'Button',
                'product_grid'  => 'Product grid',
                'cart_items'    => 'Cart items',
                'order_summary' => 'Order summary',
                'coupon'        => 'Coupon',
                'footer'        => 'Footer',
            ],

            'fields' => [
                'title'        => 'Title',
                'logo-url'     => 'Logo URL',
                'content'      => 'Content',
                'image-url'    => 'Image URL (https)',
                'alt'          => 'Alt text',
                'link'         => 'Link',
                'label'        => 'Label',
                'color'        => 'Colour (hex)',
                'source'       => 'Source',
                'count'        => 'Products (1-6)',
                'expires-days' => 'Valid for (days)',
                'footer-text'  => 'Footer text',
            ],
        ],

        'hints' => [
            'add-coupon' => 'Add a coupon block to offer a discount',
        ],

        'triggers' => [
            'cart_abandoned'        => 'Cart abandoned',
            'customer_registered'   => 'Customer registered',
            'newsletter_subscribed' => 'Newsletter subscribed',
            'order_placed'          => 'Order placed',
            'order_status_changed'  => 'Order status changed',
            'order_shipped'         => 'Order shipped',
            'order_completed'       => 'Order completed',
            'back_in_stock'         => 'Product back in stock',
            'customer_inactive'     => 'Customer inactive',
            'customer_birthday'     => 'Customer birthday',
        ],

        'types' => [
            'marketing'     => 'Marketing',
            'transactional' => 'Transactional',
        ],

        'statuses' => [
            'draft'  => 'Draft',
            'active' => 'Active',
            'paused' => 'Paused',
        ],

        'datagrid' => [
            'id'           => 'ID',
            'name'         => 'Name',
            'trigger'      => 'Trigger',
            'type'         => 'Type',
            'status'       => 'Status',
            'channels'     => 'Channels',
            'enrolled'     => 'Enrolled (30d)',
            'revenue'      => 'Revenue (30d)',
            'edit'         => 'Edit',
            'duplicate'    => 'Duplicate',
            'delete'       => 'Delete',
        ],

        'templates' => [
            'welcome' => [
                'name'        => 'Welcome series',
                'description' => 'Greet new customers by email, then follow up on WhatsApp two days later if they have not ordered yet.',
            ],
            'post_purchase' => [
                'name'        => 'Post purchase review',
                'description' => 'Ask for a product review one week after the order is completed.',
            ],
            'win_back' => [
                'name'        => 'Win back inactive customers',
                'description' => 'Email customers who have not ordered for 90 days and follow up by SMS.',
            ],
            'order_status' => [
                'name'        => 'Order status updates',
                'description' => 'Transactional SMS on every order status change, with email fallback.',
            ],
            'back_in_stock' => [
                'name'        => 'Back in stock alerts',
                'description' => 'Notify subscribers when a product they wait for becomes available again.',
            ],
            'abandoned_cart' => [
                'name'        => 'Abandoned cart recovery',
                'description' => 'Email, WhatsApp and a final email for shoppers who leave items in the cart.',
            ],
        ],

        'validation' => [
            'no-send-step'                   => 'The flow needs at least one message step.',
            'missing-step'                   => 'This step points to ":key", which does not exist.',
            'missing-entry'                  => 'The flow has no entry step.',
            'cycle'                          => 'This step is part of a loop without a wait step.',
            'template-not-approved'          => 'This WhatsApp step needs an approved template.',
            'no-provider'                    => 'No connected :channel provider is set as default for one of the sales channels.',
            'transactional-block'            => 'Transactional flows cannot use the ":block" block.',
            'coupon-rule'                    => 'The coupon block needs a cart rule with auto-generated coupons.',
            'unknown-block'                  => 'The ":block" block type does not exist.',
            'unknown-step'                   => 'This step has an unknown type.',
            'wait-needs-next'                => 'This wait step needs a following step.',
            'invalid-fallback-channel'       => 'The fallback channel of this step is invalid.',
            'invalid-consent-channel'        => 'The consent channel ":channel" is invalid.',
            'bad-wait-params'                => 'This wait step is missing its duration.',
            'bad-rule'                       => 'This condition step needs at least one valid field/op/value row.',
            'malformed-keys'                 => 'Step keys must look like s1, s2, s3.',
            'fallback-no-provider'           => 'No connected :channel provider backs the fallback for one of the sales channels.',
            'fallback-template-not-approved' => 'The fallback WhatsApp step needs an approved template.',
            'fallback-content-missing'       => 'The fallback step needs its message content (a subject and blocks for email, a body for SMS).',
        ],

        'created'           => 'Flow created.',
        'updated'           => 'Flow updated.',
        'activated'         => 'Flow activated.',
        'paused'            => 'Flow paused.',
        'resumed'           => 'Flow resumed.',
        'duplicated'        => 'Flow duplicated.',
        'deleted'           => 'Flow deleted.',
        'delete-confirm'    => 'Delete this flow? Active enrolments will be cancelled.',
        'validation-failed' => 'The flow cannot be activated yet.',
        'saved'             => 'Draft saved.',
        'preview'           => 'Preview',
        'move-up'           => 'Move up',
        'move-down'         => 'Move down',
        'active-immutable'  => 'An active flow cannot change its trigger, type or sales channels. Pause it or create a duplicate.',
        'not-active'        => 'Only an active flow can be paused.',
        'not-paused'        => 'Only a paused flow can be resumed.',
        'loops-to'          => 'Continues at :key',
        'test'              => [
            'sent'         => 'Test message queued for delivery.',
            'not-sendable' => 'That step does not send a message.',
        ],
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
