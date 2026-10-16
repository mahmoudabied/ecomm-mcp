<?php

use Illuminate\Support\Facades\Route;
use Webkul\MarketingAutomation\Http\Controllers\Admin\FlowController;
use Webkul\MarketingAutomation\Http\Controllers\Admin\FlowTestController;
use Webkul\MarketingAutomation\Http\Controllers\Admin\HealthController;
use Webkul\MarketingAutomation\Http\Controllers\Admin\ProviderController;
use Webkul\MarketingAutomation\Http\Controllers\Admin\ReportController;

Route::prefix('marketing/automation')->group(function () {
    /**
     * Providers routes.
     */
    Route::controller(ProviderController::class)->prefix('providers')->group(function () {
        Route::get('', 'index')->name('admin.marketing.automation.providers.index');

        Route::post('', 'store')->name('admin.marketing.automation.providers.store');

        Route::put('defaults', 'updateDefaults')->name('admin.marketing.automation.providers.defaults');

        Route::get('{provider}', 'show')->name('admin.marketing.automation.providers.show');

        Route::put('{provider}', 'update')->name('admin.marketing.automation.providers.update');

        Route::delete('{provider}', 'destroy')->name('admin.marketing.automation.providers.delete');

        Route::post('{provider}/test', 'test')->name('admin.marketing.automation.providers.test');

        Route::post('{provider}/sync-templates', 'syncTemplates')->name('admin.marketing.automation.providers.sync_templates');
    });

    /**
     * Flows routes.
     */
    Route::controller(FlowController::class)->prefix('flows')->group(function () {
        Route::get('', 'index')->name('admin.marketing.automation.flows.index');

        Route::get('templates', 'templates')->name('admin.marketing.automation.flows.templates');

        Route::post('', 'store')->name('admin.marketing.automation.flows.store');

        Route::get('{flow}/edit', 'edit')->name('admin.marketing.automation.flows.edit');

        Route::put('{flow}', 'update')->name('admin.marketing.automation.flows.update');

        Route::post('{flow}/activate', 'activate')->name('admin.marketing.automation.flows.activate');

        Route::post('{flow}/pause', 'pause')->name('admin.marketing.automation.flows.pause');

        Route::post('{flow}/resume', 'resume')->name('admin.marketing.automation.flows.resume');

        Route::post('{flow}/duplicate', 'duplicate')->name('admin.marketing.automation.flows.duplicate');

        Route::delete('{flow}', 'destroy')->name('admin.marketing.automation.flows.delete');

        Route::post('{flow}/steps/{stepKey}/test', [FlowTestController::class, 'send'])
            ->name('admin.marketing.automation.flows.test');

        Route::get('{flow}/preview/{stepKey}', 'preview')->name('admin.marketing.automation.flows.preview');

        Route::post('{flow}/preview', 'previewDraft')->name('admin.marketing.automation.flows.preview_draft');
    });

    /**
     * Report routes.
     */
    Route::controller(ReportController::class)->prefix('flows')->group(function () {
        Route::get('{flow}/report', 'report')->name('admin.marketing.automation.flows.report');

        Route::get('{flow}/enrolments', 'enrolments')->name('admin.marketing.automation.flows.enrolments');
    });

    Route::get('enrolments/{enrolment}/journey', [ReportController::class, 'journey'])
        ->name('admin.marketing.automation.enrolments.journey');

    /**
     * Health route (also linked as a tab from the providers page).
     */
    Route::get('health', [HealthController::class, 'index'])
        ->name('admin.marketing.automation.health.index');
});
