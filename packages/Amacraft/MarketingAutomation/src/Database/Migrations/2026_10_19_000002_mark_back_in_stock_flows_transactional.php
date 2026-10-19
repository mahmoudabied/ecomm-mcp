<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('automation_flows')
            ->where('template_key', 'back_in_stock')
            ->where('type', 'marketing')
            ->update(['type' => 'transactional']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('automation_flows')
            ->where('template_key', 'back_in_stock')
            ->where('type', 'transactional')
            ->update(['type' => 'marketing']);
    }
};
