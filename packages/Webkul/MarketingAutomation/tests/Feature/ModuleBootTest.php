<?php

use Webkul\MarketingAutomation\Providers\MarketingAutomationServiceProvider;

it('boots the MarketingAutomation module providers', function () {
    expect(app()->getProviders(MarketingAutomationServiceProvider::class))->not->toBeEmpty();
});

it('resolves the marketing_automation translation namespace', function () {
    expect(trans('marketing_automation::app.test'))->toBeString();
});
