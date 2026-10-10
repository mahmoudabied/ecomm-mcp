<?php

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Facades\Route;
use Webkul\MarketingAutomation\Http\Controllers\Shop\BackInStockController;
use Webkul\MarketingAutomation\Http\Controllers\Shop\UnsubscribeController;

/*
 * Shop routes for storefront automation features.
 */
Route::post('automation/back-in-stock', [BackInStockController::class, 'store'])
    ->name('shop.automation.back-in-stock')
    ->middleware('throttle:10,1');

/*
 * One-click unsubscribe (FR-023). The POST skips CSRF because mail clients
 * send RFC 8058 one-click requests without a session; the signed token is
 * the authentication.
 */
Route::controller(UnsubscribeController::class)
    ->prefix('automation/unsubscribe')
    ->middleware('throttle:30,1')
    ->group(function () {
        Route::get('{token}', 'show')->name('shop.automation.unsubscribe');

        Route::post('{token}', 'confirm')
            ->name('shop.automation.unsubscribe.confirm')
            ->withoutMiddleware(VerifyCsrfToken::class);
    });
