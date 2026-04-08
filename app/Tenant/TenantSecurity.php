<?php

namespace App\Tenant;

use Illuminate\Support\Facades\Gate;
use Spatie\Multitenancy\Models\Tenant;

class TenantSecurity
{
    public function configureIsolation()
    {
        // Configure tenant-specific policies
        Gate::define('access-tenant', function ($user, Tenant $tenant) {
            return $user->tenant_id === $tenant->id;
        });

        // Configure tenant-specific middleware
        $this->configureTenantMiddleware();

        // Configure tenant-specific storage
        $this->configureTenantStorage();
    }

    protected function configureTenantMiddleware()
    {
        app('router')->aliasMiddleware('tenant.auth', \App\Http\Middleware\EnsureTenantAccess::class);
    }

    protected function configureTenantStorage()
    {
        // Configure tenant-specific storage disk
        config(['filesystems.disks.tenant' => [
            'driver' => 'local',
            'root' => storage_path('app/tenants/' . tenant('id')),
            'url' => env('APP_URL').'/storage/tenants/' . tenant('id'),
            'visibility' => 'public',
        ]]);
    }
}
