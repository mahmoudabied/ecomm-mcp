<?php

use Illuminate\Support\Facades\Route;
use Webkul\MarketingAutomation\Http\Controllers\Admin\HealthController;
use Webkul\MarketingAutomation\Http\Controllers\Admin\PlaceholderController;

Route::prefix('marketing/automation')->group(function () {
    Route::get('flows', [PlaceholderController::class, 'index'])
        ->name('admin.marketing.automation.flows.index');

    Route::get('providers', [PlaceholderController::class, 'index'])
        ->name('admin.marketing.automation.providers.index');

    Route::get('health', [HealthController::class, 'index'])
        ->name('admin.marketing.automation.health.index');
});
