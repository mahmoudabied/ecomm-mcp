<?php

use Illuminate\Support\Arr;

it('keeps the english and arabic translation keys identical', function (string $package, string $namespace) {
    $en = Arr::dot(require __DIR__."/../../../{$package}/src/Resources/lang/en/app.php");
    $ar = Arr::dot(require __DIR__."/../../../{$package}/src/Resources/lang/ar/app.php");

    expect(array_keys($ar))->toEqualCanonicalizing(array_keys($en));
})->with([
    'MarketingAutomation' => ['MarketingAutomation', 'marketing_automation'],
    'Klaviyo'             => ['Klaviyo', 'klaviyo'],
]);
