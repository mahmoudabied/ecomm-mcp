<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('automation_attributions', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('order_id');
            $table->unsignedInteger('flow_id');
            $table->string('step_key', 20);
            $table->unsignedBigInteger('message_log_id')->nullable();
            $table->unsignedInteger('channel_id');
            $table->string('model', 20);
            $table->decimal('revenue', 12, 4)->default(0);
            $table->string('currency', 10);
            $table->timestamp('attributed_at');
            $table->timestamps();

            $table->foreign('order_id')->references('id')->on('orders')->cascadeOnDelete();
            $table->foreign('message_log_id')->references('id')->on('automation_message_logs')->nullOnDelete();
            $table->foreign('channel_id')->references('id')->on('channels')->cascadeOnDelete();

            $table->unique('order_id');
        });

        Schema::create('automation_step_stats', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->date('date');
            $table->unsignedInteger('flow_id');
            $table->string('step_key', 20);
            $table->unsignedInteger('channel_id');
            $table->string('metric', 20);
            $table->string('skip_reason', 50)->default('');
            $table->unsignedInteger('count')->default(0);
            $table->decimal('revenue', 14, 4)->default(0);
            $table->timestamps();

            $table->foreign('channel_id')->references('id')->on('channels')->cascadeOnDelete();

            $table->unique(['date', 'flow_id', 'step_key', 'channel_id', 'metric', 'skip_reason'], 'automation_step_stats_daily_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('automation_step_stats');

        Schema::dropIfExists('automation_attributions');
    }
};
