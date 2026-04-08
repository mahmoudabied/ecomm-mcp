<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TenantDatabaseSeeder extends Seeder
{
    public function run()
    {
        // Seed basic tenant data
        $this->seedTenantConfigurations();
        $this->seedTenantProducts();
        $this->seedTenantCategories();
    }

    protected function seedTenantConfigurations()
    {
        // Implement tenant-specific configuration seeding
        DB::table('core_config')->insert([
            [
                'code' => 'general',
                'value' => json_encode([
                    'locale' => 'en',
                    'timezone' => 'UTC',
                    'currency' => 'USD'
                ])
            ]
        ]);
    }

    protected function seedTenantProducts()
    {
        // Implement tenant-specific product seeding
    }

    protected function seedTenantCategories()
    {
        // Implement tenant-specific category seeding
    }
}
