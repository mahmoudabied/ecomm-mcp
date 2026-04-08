<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\TenantController;

Route::group(['middleware' => ['web', 'admin']], function () {
    Route::prefix(config('app.admin_url'))->group(function () {
        // Tenant Management Routes
        Route::group(['prefix' => 'tenants'], function () {
            Route::get('/', [TenantController::class, 'index'])->name('admin.tenants.index');
            Route::get('/create', [TenantController::class, 'create'])->name('admin.tenants.create');
            Route::post('/', [TenantController::class, 'store'])->name('admin.tenants.store');
            Route::get('/{tenant}/edit', [TenantController::class, 'edit'])->name('admin.tenants.edit');
            Route::put('/{tenant}', [TenantController::class, 'update'])->name('admin.tenants.update');
            Route::delete('/{tenant}', [TenantController::class, 'destroy'])->name('admin.tenants.destroy');
        });
    });
});
