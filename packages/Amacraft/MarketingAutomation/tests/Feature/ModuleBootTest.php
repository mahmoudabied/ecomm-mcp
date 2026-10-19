<?php

use Amacraft\MarketingAutomation\Providers\MarketingAutomationServiceProvider;
use Illuminate\Support\ServiceProvider;

it('boots the MarketingAutomation module providers', function () {
    expect(app()->getProviders(MarketingAutomationServiceProvider::class))->not->toBeEmpty();
});

it('resolves the marketing_automation translation namespace', function () {
    expect(trans('marketing_automation::app.test'))->toBeString();
});

it('publishes view and translation overrides where Laravel looks for the marketing_automation namespace', function () {
    $views = ServiceProvider::pathsToPublish(MarketingAutomationServiceProvider::class, 'ma-views');

    $lang = ServiceProvider::pathsToPublish(MarketingAutomationServiceProvider::class, 'ma-lang');

    expect(array_values($views))->toBe([resource_path('views/vendor/marketing_automation')])
        ->and(array_values($lang))->toBe([lang_path('vendor/marketing_automation')]);
});
