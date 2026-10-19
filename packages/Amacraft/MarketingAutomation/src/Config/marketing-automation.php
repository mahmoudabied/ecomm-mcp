<?php

return [

    /**
     * Queue names the package dispatches its jobs on. Set a value to null to
     * use the default queue of the queue connection.
     */
    'queues' => [
        'automation' => env('MA_QUEUE', 'automation'),

        'klaviyo' => env('KLAVIYO_QUEUE', 'klaviyo'),
    ],

    /**
     * The WhatsApp utility template used for double opt-in confirmations.
     * WhatsApp rejects free text outside a conversation, so an approved
     * template with one body variable (the confirmation link) is required.
     */
    'confirmation' => [
        'whatsapp_template' => env('MA_WHATSAPP_CONFIRM_TEMPLATE', 'consent_confirm'),
    ],
];
