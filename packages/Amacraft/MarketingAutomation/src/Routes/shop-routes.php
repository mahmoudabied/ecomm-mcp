<?php

use Amacraft\MarketingAutomation\Http\Controllers\Shop\BackInStockController;
use Amacraft\MarketingAutomation\Http\Controllers\Shop\CartRestoreController;
use Amacraft\MarketingAutomation\Http\Controllers\Shop\CheckoutContactController;
use Amacraft\MarketingAutomation\Http\Controllers\Shop\ConsentConfirmationController;
use Amacraft\MarketingAutomation\Http\Controllers\Shop\MarketingPreferencesController;
use Amacraft\MarketingAutomation\Http\Controllers\Shop\UnsubscribeController;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Facades\Route;

/**
 * Back-in-stock subscription routes.
 */
Route::post('automation/back-in-stock', [BackInStockController::class, 'store'])
    ->name('shop.automation.back-in-stock')
    ->middleware('throttle:10,1');

/**
 * Abandoned cart restore routes; the signed token is the capability.
 */
Route::get('automation/cart/restore/{token}', [CartRestoreController::class, 'restore'])
    ->name('shop.automation.cart.restore');

/**
 * Checkout contact capture routes.
 */
Route::post('automation/checkout-contact', [CheckoutContactController::class, 'store'])
    ->name('shop.automation.checkout-contact')
    ->middleware('throttle:10,1');

/**
 * Customer marketing preferences routes.
 */
Route::controller(MarketingPreferencesController::class)
    ->prefix('customer/account/marketing-preferences')
    ->middleware('customer')
    ->group(function () {
        Route::get('', 'index')->name('shop.customers.account.marketing-preferences.index');

        Route::put('', 'update')->name('shop.customers.account.marketing-preferences.update');

        Route::get('export', 'export')->name('shop.customers.account.marketing-preferences.export');
    });

/**
 * One-click unsubscribe routes; mail clients post without a session, so the signed token replaces CSRF.
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

/**
 * Double opt-in confirmation routes; the signed token is the proof the
 * requester owns the address, so it replaces a session.
 */
Route::controller(ConsentConfirmationController::class)
    ->prefix('automation/consent/confirm')
    ->middleware('throttle:30,1')
    ->group(function () {
        Route::get('{token}', 'show')->name('shop.automation.consent.confirm');

        Route::post('{token}', 'confirm')->name('shop.automation.consent.confirm.submit');
    });
