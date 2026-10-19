<?php

use Amacraft\MarketingAutomation\Services\Providers\MessageBirdSmsProvider;
use Amacraft\MarketingAutomation\Services\Providers\MetaCloudWhatsAppProvider;
use Amacraft\MarketingAutomation\Services\Providers\MsegatSmsProvider;
use Amacraft\MarketingAutomation\Services\Providers\TaqnyatSmsProvider;
use Amacraft\MarketingAutomation\Services\Providers\TwilioSmsProvider;
use Amacraft\MarketingAutomation\Services\Providers\TwilioWhatsAppProvider;
use Amacraft\MarketingAutomation\Services\Providers\UnifonicSmsProvider;
use Amacraft\MarketingAutomation\Services\Providers\VonageSmsProvider;

return [
    'drivers' => [
        'twilio' => [
            'class'        => TwilioSmsProvider::class,
            'channel'      => 'sms',
            'label'        => 'Twilio',
            'webhook_auth' => 'signature',
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
            'class'        => MetaCloudWhatsAppProvider::class,
            'channel'      => 'whatsapp',
            'label'        => 'Meta WhatsApp Cloud',
            'webhook_auth' => 'signature',
        ],

        'twilio_whatsapp' => [
            'class'        => TwilioWhatsAppProvider::class,
            'channel'      => 'whatsapp',
            'label'        => 'Twilio WhatsApp',
            'webhook_auth' => 'signature',
        ],
    ],
];
