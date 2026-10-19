<?php

use Amacraft\Klaviyo\Providers\KlaviyoServiceProvider;

it('boots the Klaviyo module providers', function () {
    expect(app()->getProviders(KlaviyoServiceProvider::class))->not->toBeEmpty();
});

it('resolves the klaviyo translation namespace', function () {
    expect(trans('klaviyo::app.menu'))->toBeArray();
});
