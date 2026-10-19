<?php

use Illuminate\Support\Facades\Route;
use Amacraft\Klaviyo\Http\Controllers\Admin\KlaviyoController;

Route::prefix('marketing/klaviyo')->group(function () {
    Route::controller(KlaviyoController::class)->group(function () {
        Route::get('', 'index')->name('admin.marketing.klaviyo.index');

        Route::post('connect', 'connect')->name('admin.marketing.klaviyo.connect');

        Route::post('disconnect', 'disconnect')->name('admin.marketing.klaviyo.disconnect');

        Route::post('sync', 'sync')->name('admin.marketing.klaviyo.sync');

        Route::get('sync/status', 'syncStatus')->name('admin.marketing.klaviyo.sync_status');

        Route::post('outbox/retry-failed', 'retryFailed')->name('admin.marketing.klaviyo.retry_failed');
    });
});
