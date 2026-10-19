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
];
