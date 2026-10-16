<?php

use Webkul\Faker\Helpers\Product as ProductFaker;
use Webkul\MarketingAutomation\Rendering\MessageRenderer;

/**
 * Render an email step through the full renderer pipeline.
 */
function blocksStep(array $blocks, array $subject = ['en' => 'Hello', 'ar' => 'مرحبا']): array
{
    return ['subject' => $subject, 'blocks' => [
        'en' => $blocks,
        'ar' => $blocks,
    ]];
}

it('renders every email block type with its props', function (string $type, array $props, array $context, array $expected) {
    $rendered = app(MessageRenderer::class)->renderEmail(
        blocksStep([['type' => $type, ...$props]]),
        $context,
        'en',
    );

    foreach ($expected as $fragment) {
        expect($rendered['html'])->toContain($fragment);
    }
})->with([
    'header' => [
        'header',
        ['title'  => 'Season sale', 'logo_url' => 'https://shop.test/logo.png'],
        ['locale' => 'en', 'direction' => 'ltr'],
        ['Season sale', 'https://shop.test/logo.png'],
    ],
    'text' => [
        'text',
        ['content' => 'Hello {{ customer.first_name }}'],
        ['locale'  => 'en', 'direction' => 'ltr', 'customer' => ['first_name' => 'Sara']],
        ['Hello Sara'],
    ],
    'image' => [
        'image',
        ['src'    => 'https://shop.test/banner.jpg', 'alt' => 'Banner'],
        ['locale' => 'en', 'direction' => 'ltr'],
        ['https://shop.test/banner.jpg', 'Banner'],
    ],
    'button' => [
        'button',
        ['label'  => 'Shop now', 'href' => 'https://shop.test/sale', 'color' => '#0f172a'],
        ['locale' => 'en', 'direction' => 'ltr'],
        ['Shop now', 'https://shop.test/sale'],
    ],
    'cart_items' => [
        'cart_items',
        [],
        ['locale' => 'en', 'direction' => 'ltr', 'cart' => ['items' => [
            ['name' => 'Canvas Bag', 'qty' => 2, 'price_formatted' => '$50.00', 'image_url' => ''],
        ]]],
        ['Canvas Bag', '$50.00'],
    ],
    'order_summary' => [
        'order_summary',
        [],
        ['locale' => 'en', 'direction' => 'ltr', 'order' => [
            'items'  => [['name' => 'Mug', 'qty' => 1, 'price_formatted' => '$9.00']],
            'total'  => '$9.00',
        ]],
        ['Mug', '$9.00'],
    ],
    'coupon' => [
        'coupon',
        ['label'  => 'Your private discount'],
        ['locale' => 'en', 'direction' => 'ltr', 'coupon' => ['code' => 'MA-AB12CD34EF']],
        ['Your private discount', 'MA-AB12CD34EF'],
    ],
    'footer' => [
        'footer',
        ['text'   => 'Acme Store, Riyadh'],
        ['locale' => 'en', 'direction' => 'ltr', 'unsubscribe_url' => 'https://shop.test/automation/unsubscribe/tok'],
        ['Acme Store, Riyadh', 'https://shop.test/automation/unsubscribe/tok'],
    ],
]);

it('renders a product_grid block from recommendations and hides the cart products', function () {
    $baseProduct = (new ProductFaker)->getSimpleProductFactory()->create();

    $recommended = (new ProductFaker)->getSimpleProductFactory()->create();

    DB::table('product_relations')->insert([
        'parent_id' => $baseProduct->id,
        'child_id'  => $recommended->id,
    ]);

    $rendered = app(MessageRenderer::class)->renderEmail(
        blocksStep([['type' => 'product_grid', 'source' => 'related', 'count' => 2]]),
        [
            'locale'     => 'en',
            'direction'  => 'ltr',
            'channel_id' => core()->getAllChannels()->first()->id,
            'cart'       => ['items' => [['product_id' => $baseProduct->id, 'name' => $baseProduct->name]]],
        ],
        'en',
    );

    expect($rendered['html'])->toContain($recommended->name)
        ->and($rendered['html'])->not->toContain($baseProduct->name);
});

it('escapes merchant text containing script tags', function () {
    $rendered = app(MessageRenderer::class)->renderEmail(
        blocksStep([['type' => 'text', 'text' => 'Hi <script>alert(1)</script>']]),
        ['locale' => 'en', 'direction' => 'ltr'],
        'en',
    );

    expect($rendered['html'])->toContain('&lt;script&gt;')
        ->and($rendered['html'])->not->toContain('<script>alert(1)</script>');
});

it('renders the arabic version with a right to left wrapper and arabic subject', function () {
    $rendered = app(MessageRenderer::class)->renderEmail(
        [
            'subject' => ['en' => 'Hello', 'ar' => 'مرحبا'],
            'blocks'  => [
                'en' => [['type' => 'text', 'text' => 'English body']],
                'ar' => [['type' => 'text', 'text' => 'نص عربي']],
            ],
        ],
        ['locale' => 'ar', 'direction' => 'rtl'],
        'ar',
    );

    expect($rendered['html'])->toContain('dir="rtl"')
        ->and($rendered['subject'])->toBe('مرحبا')
        ->and($rendered['html'])->toContain('نص عربي');
});

it('falls back to the default locale content when the arabic version is missing', function () {
    $rendered = app(MessageRenderer::class)->renderEmail(
        [
            'subject' => ['en' => 'Hello'],
            'blocks'  => ['en' => [['type' => 'text', 'text' => 'English body']]],
        ],
        ['locale' => 'ar', 'direction' => 'rtl'],
        'ar',
    );

    expect($rendered['subject'])->toBe('Hello')
        ->and($rendered['html'])->toContain('English body');
});

it('renders the fallback text for a missing first name', function () {
    $rendered = app(MessageRenderer::class)->renderEmail(
        blocksStep([['type' => 'text', 'text' => 'Hi {{ customer.first_name | default: "there" }}']]),
        ['locale' => 'en', 'direction' => 'ltr', 'customer' => ['first_name' => null]],
        'en',
    );

    expect($rendered['html'])->toContain('Hi there');
});

it('only allows http urls or variables in a button href', function (string $href, string $expected) {
    $rendered = app(MessageRenderer::class)->renderEmail(
        blocksStep([['type' => 'button', 'label' => 'Go', 'href' => $href]]),
        [
            'locale'    => 'en',
            'direction' => 'ltr',
            'cart'      => ['restore_url' => 'https://shop.test/automation/cart/restore/tok123'],
        ],
        'en',
    );

    expect($rendered['html'])->toContain($expected);
})->with([
    'https link'            => ['https://shop.test/sale', 'href="https://shop.test/sale"'],
    'restore variable'      => ['{{ cart.restore_url }}', 'href="https://shop.test/automation/cart/restore/tok123"'],
    'javascript is dropped' => ['javascript:alert(1)', 'href="#"'],
]);

it('skips unknown block types instead of crashing the render', function () {
    $rendered = app(MessageRenderer::class)->renderEmail(
        blocksStep([
            ['type' => 'not_a_real_block', 'text' => 'broken'],
            ['type' => 'text', 'text' => 'still here'],
        ]),
        ['locale' => 'en', 'direction' => 'ltr'],
        'en',
    );

    expect($rendered['html'])->toContain('still here')
        ->and($rendered['html'])->not->toContain('broken');
});
