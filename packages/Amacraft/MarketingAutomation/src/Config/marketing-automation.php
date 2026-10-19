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

    /**
     * The public URL providers sign their webhooks against. Set it when the
     * store sits behind a proxy that does not pass X-Forwarded-Proto/Host, so
     * Twilio signature checks keep matching.
     */
    'webhooks' => [
        'public_base_url' => env('MA_PUBLIC_BASE_URL'),
    ],
];
