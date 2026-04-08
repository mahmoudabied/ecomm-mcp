<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddCustomFieldsToTenantsTable extends Migration
{
    public function up()
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('company_name')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('contact_phone')->nullable();
            $table->string('subscription_plan')->nullable();
            $table->integer('max_users')->default(5);
            $table->integer('max_products')->default(100);
            $table->string('theme')->default('default');
            $table->json('settings')->nullable();
            $table->string('status')->default('active');
        });
    }

    public function down()
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn([
                'company_name',
                'contact_email',
                'contact_phone',
                'subscription_plan',
                'max_users',
                'max_products',
                'theme',
                'settings',
                'status'
            ]);
        });
    }
}
