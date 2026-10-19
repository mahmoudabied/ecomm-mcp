<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('automation_back_in_stock_subscriptions', function (Blueprint $table) {
            $table->string('trigger_key', 100)->nullable()->after('status');

            $table->index('trigger_key');
        });

        DB::table('automation_back_in_stock_subscriptions')
            ->whereNull('trigger_key')
            ->orderBy('id')
            ->chunkById(500, function ($subscriptions) {
                foreach ($subscriptions as $subscription) {
                    DB::table('automation_back_in_stock_subscriptions')
                        ->where('id', $subscription->id)
                        ->update(['trigger_key' => "bis:{$subscription->id}"]);
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('automation_back_in_stock_subscriptions', function (Blueprint $table) {
            $table->dropIndex(['trigger_key']);

            $table->dropColumn('trigger_key');
        });
    }
};
