<?php

use Webkul\MarketingAutomation\Services\Providers\MessageBirdSmsProvider;
use Webkul\MarketingAutomation\Services\Providers\MetaCloudWhatsAppProvider;
use Webkul\MarketingAutomation\Services\Providers\MsegatSmsProvider;
use Webkul\MarketingAutomation\Services\Providers\TaqnyatSmsProvider;
use Webkul\MarketingAutomation\Services\Providers\TwilioSmsProvider;
use Webkul\MarketingAutomation\Services\Providers\TwilioWhatsAppProvider;
use Webkul\MarketingAutomation\Services\Providers\UnifonicSmsProvider;
use Webkul\MarketingAutomation\Services\Providers\VonageSmsProvider;

return [
    'drivers' => [
        'twilio' => [
            'class'   => TwilioSmsProvider::class,
            'channel' => 'sms',
            'label'   => 'Twilio',
        ],

        'vonage' => [
            'class'   => VonageSmsProvider::class,
            'channel' => 'sms',
            'label'   => 'Vonage',
        ],

        'messagebird' => [
            'class'   => MessageBirdSmsProvider::class,
            'channel' => 'sms',
            'label'   => 'MessageBird',
        ],

        'unifonic' => [
            'class'   => UnifonicSmsProvider::class,
            'channel' => 'sms',
            'label'   => 'Unifonic',
        ],

        'msegat' => [
            'class'   => MsegatSmsProvider::class,
            'channel' => 'sms',
            'label'   => 'Msegat',
        ],

        'taqnyat' => [
            'class'   => TaqnyatSmsProvider::class,
            'channel' => 'sms',
            'label'   => 'Taqnyat',
        ],

        'meta_cloud' => [
            'class'   => MetaCloudWhatsAppProvider::class,
            'channel' => 'whatsapp',
            'label'   => 'Meta WhatsApp Cloud',
        ],

        'twilio_whatsapp' => [
            'class'   => TwilioWhatsAppProvider::class,
            'channel' => 'whatsapp',
            'label'   => 'Twilio WhatsApp',
        ],
    ],
];
