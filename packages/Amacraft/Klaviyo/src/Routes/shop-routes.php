<?php

use Illuminate\Support\Facades\Route;
use Amacraft\Klaviyo\Http\Controllers\TrackController;

Route::prefix('klaviyo')->group(function () {
    Route::post('track/viewed-product', [TrackController::class, 'viewedProduct'])
        ->name('klaviyo.track.viewed-product')
        ->middleware('throttle:30,1');
});
