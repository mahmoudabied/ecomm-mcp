<?php

namespace App\Tenant;

use Illuminate\Support\Facades\DB;
use Spatie\Multitenancy\Models\Tenant;
use Illuminate\Support\Facades\Artisan;

class TenantDatabaseManager
{
    public function create(Tenant $tenant)
    {
        // Create new database for tenant
        DB::statement("CREATE DATABASE IF NOT EXISTS {$tenant->database}");

        // Configure tenant database connection
        config(['database.connections.tenant' => [
            'driver' => 'mysql',
            'host' => config('database.connections.mysql.host'),
            'database' => $tenant->database,
            'username' => config('database.connections.mysql.username'),
            'password' => config('database.connections.mysql.password'),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
        ]]);

        // Switch to tenant database
        DB::purge('tenant');
        DB::reconnect('tenant');

        // Run migrations for tenant
        Artisan::call('migrate', [
            '--database' => 'tenant',
            '--path' => 'database/migrations/tenant',
            '--force' => true
        ]);

        // Run seeders for tenant
        if (config('tenant.seed_on_create', false)) {
            Artisan::call('db:seed', [
                '--class' => 'TenantDatabaseSeeder',
                '--force' => true
            ]);
        }
    }

    public function delete(Tenant $tenant)
    {
        // Drop tenant database
        DB::statement("DROP DATABASE IF EXISTS {$tenant->database}");
    }
}
