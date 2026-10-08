<?php

use Webkul\MarketingAutomation\Rendering\TemplateRenderer;

it('renders variables with fallback defaults', function () {
    $renderer = new TemplateRenderer;

    expect($renderer->render('Hi {{ customer.first_name | default: "there" }}', [
        'customer' => ['first_name' => 'Sara'],
    ]))->toBe('Hi Sara')
        ->and($renderer->render('Hi {{ customer.first_name | default: "there" }}', [
            'customer' => [],
        ]))->toBe('Hi there');
});

it('renders unknown tokens as empty strings', function () {
    expect((new TemplateRenderer)->render('Hello {{ secret.password }}', [
        'customer' => ['first_name' => 'Sara'],
    ]))->toBe('Hello ');
});

it('does not escape values, leaving that to the caller', function () {
    expect((new TemplateRenderer)->render('{{ customer.first_name }}', [
        'customer' => ['first_name' => '<b>'],
    ]))->toBe('<b>');
});

it('leaves hostile input untouched apart from tokens', function () {
    $renderer = new TemplateRenderer;

    $template = '@php echo "hi"; {!! customer.first_name !!} {{ customer.first_name }}';

    expect($renderer->render($template, ['customer' => ['first_name' => 'Sara']]))
        ->toBe('@php echo "hi"; {!! customer.first_name !!} Sara');
});

it('renders arrays and objects as empty strings', function () {
    $renderer = new TemplateRenderer;

    expect($renderer->render('Items: {{ cart.items }}', [
        'cart' => ['items' => ['a', 'b']],
    ]))->toBe('Items: ')
        ->and($renderer->render('X: {{ cart }}', [
            'cart' => ['total' => 10],
        ]))->toBe('X: ');
});

it('exposes the documented variable whitelist', function () {
    expect((new TemplateRenderer)->variables())->toContain(
        'customer.first_name',
        'cart.restore_url',
        'order.increment_id',
        'coupon.code',
        'unsubscribe_url',
    );
});
