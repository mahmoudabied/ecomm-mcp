<?php

use Amacraft\MarketingAutomation\Models\WhatsAppTemplate;
use Amacraft\MarketingAutomation\Rendering\MessageRenderer;

it('picks the arabic sms body for an arabic contact', function () {
    $body = app(MessageRenderer::class)->renderText(
        ['body' => ['en' => 'Your cart is waiting', 'ar' => 'سلتك تنتظرك']],
        ['locale' => 'ar'],
        'ar',
    );

    expect($body)->toBe('سلتك تنتظرك');
});

it('falls back to the default locale sms body when the contact locale is missing', function () {
    $body = app(MessageRenderer::class)->renderText(
        ['body' => ['en' => 'Your cart is waiting']],
        ['locale' => 'ar'],
        'ar',
    );

    expect($body)->toBe('Your cart is waiting');
});

it('selects the whatsapp template language per locale and falls back to the template default', function () {
    $template = WhatsAppTemplate::factory()->create([
        'name'     => 'cart_recovery',
        'language' => 'en_US',
    ]);

    $localised = app(MessageRenderer::class)->renderWhatsApp(
        [
            'template_id' => $template->id,
            'language'    => ['en' => 'en_US', 'ar' => 'ar'],
            'variables'   => ['1' => '{{ customer.first_name }}'],
        ],
        ['locale' => 'ar', 'customer' => ['first_name' => 'سارة']],
        'ar',
    );

    $fallback = app(MessageRenderer::class)->renderWhatsApp(
        [
            'template_id' => $template->id,
            'variables'   => ['1' => '{{ customer.first_name }}'],
        ],
        ['locale' => 'ar', 'customer' => ['first_name' => 'سارة']],
        'ar',
    );

    expect($localised->language)->toBe('ar')
        ->and($localised->bodyParams)->toBe([1 => 'سارة'])
        ->and($fallback->language)->toBe('en_US');
});
