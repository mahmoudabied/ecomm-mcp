<?php

use Illuminate\Support\Facades\Route;
use Webkul\MarketingAutomation\Http\Controllers\WebhookController;

/*
 * Public webhook routes. CSRF is skipped because providers cannot carry a
 * session token; every request is authenticated by the driver's signature
 * or the shared ?s= secret inside the controller instead.
 *
 * Providers deliver status bursts from a handful of IPs, so receive is not
 * throttled per IP. GET only carries a generous limit because it also serves
 * the Meta verify handshake (and GET-style delivery reports).
 */
Route::prefix('automation/webhooks')->group(function () {
    Route::get('{driver}/{uuid}', [WebhookController::class, 'verify'])
        ->middleware('throttle:600,1')
        ->name('automation.webhooks.verify');

    Route::post('{driver}/{uuid}', [WebhookController::class, 'receive'])
        ->name('automation.webhooks.receive');
});
