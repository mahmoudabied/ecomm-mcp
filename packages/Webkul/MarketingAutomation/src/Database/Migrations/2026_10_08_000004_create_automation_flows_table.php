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
        Schema::create('automation_flows', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->string('template_key', 50)->nullable();
            $table->string('trigger_type', 50);
            $table->json('trigger_config')->nullable();
            $table->string('type', 20);
            $table->string('status', 20)->default('draft');
            $table->unsignedInteger('reentry_window_hours')->default(168);
            $table->json('exit_goals')->nullable();
            $table->json('draft_definition')->nullable();
            $table->unsignedInteger('current_version_id')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('paused_at')->nullable();
            $table->timestamp('resumed_at')->nullable();
            $table->unsignedInteger('created_by')->nullable();
            $table->timestamps();

            $table->foreign('created_by')->references('id')->on('admins')->nullOnDelete();

            $table->index('current_version_id');
        });

        Schema::create('automation_flow_channels', function (Blueprint $table) {
            $table->unsignedInteger('flow_id');
            $table->unsignedInteger('channel_id');
            $table->timestamps();

            $table->foreign('flow_id')->references('id')->on('automation_flows')->cascadeOnDelete();
            $table->foreign('channel_id')->references('id')->on('channels')->cascadeOnDelete();

            $table->primary(['flow_id', 'channel_id']);
        });

        Schema::create('automation_flow_versions', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('flow_id');
            $table->unsignedInteger('version');
            $table->json('definition');
            $table->char('definition_hash', 64);
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('flow_id')->references('id')->on('automation_flows')->cascadeOnDelete();

            $table->unique(['flow_id', 'version']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('automation_flow_versions');

        Schema::dropIfExists('automation_flow_channels');

        Schema::dropIfExists('automation_flows');
    }
};
