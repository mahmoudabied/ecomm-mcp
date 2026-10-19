<?php

use Amacraft\MarketingAutomation\Rendering\MessageRenderer;

it('renders an email step with empty or malformed blocks as an empty body', function (mixed $blocks) {
    $rendered = app(MessageRenderer::class)->renderEmail(
        ['subject' => ['en' => 'Hi'], 'blocks' => $blocks],
        ['locale' => 'en', 'direction' => 'ltr'],
        'en',
    );

    $renderedEmpty = app(MessageRenderer::class)->renderEmail(
        ['subject' => ['en' => 'Hi'], 'blocks' => ['en' => []]],
        ['locale' => 'en', 'direction' => 'ltr'],
        'en',
    );

    expect($rendered['subject'])->toBe('Hi')
        ->and($rendered['html'])->toBe($renderedEmpty['html']);
})->with([
    'empty map'          => [[]],
    'empty locale list'  => [['en' => []]],
    'scalar locale'      => [['en' => '']],
    'non-array entries'  => [['en' => ['x', 1, null]]],
]);

it('adds the unsubscribe footer only when the context carries a link', function () {
    $step = ['subject' => ['en' => 'Hi'], 'blocks' => ['en' => [['type' => 'text', 'text' => 'Body']]]];

    $withLink = app(MessageRenderer::class)->renderEmail($step, ['locale' => 'en', 'unsubscribe_url' => 'https://shop.test/u/abc'], 'en');

    $withoutLink = app(MessageRenderer::class)->renderEmail($step, ['locale' => 'en', 'unsubscribe_url' => null], 'en');

    expect($withLink['html'])->toContain('https://shop.test/u/abc')
        ->and($withoutLink['html'])->not->toContain(trans('marketing_automation::app.emails.unsubscribe'));
});
