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
        Schema::create('automation_enrolments', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('flow_id');
            $table->unsignedInteger('flow_version_id');
            $table->unsignedInteger('contact_id');
            $table->unsignedInteger('channel_id');
            $table->string('trigger_key', 100);
            $table->json('trigger_payload')->nullable();
            $table->string('status', 20);
            $table->string('exit_reason', 50)->nullable();
            $table->string('current_step_key', 20)->nullable();
            $table->timestamp('next_run_at')->nullable();
            $table->timestamp('locked_until')->nullable();
            $table->char('lock_token', 26)->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->foreign('flow_id')->references('id')->on('automation_flows')->cascadeOnDelete();
            $table->foreign('flow_version_id')->references('id')->on('automation_flow_versions')->cascadeOnDelete();
            $table->foreign('contact_id')->references('id')->on('automation_contacts')->cascadeOnDelete();
            $table->foreign('channel_id')->references('id')->on('channels')->cascadeOnDelete();

            $table->unique(['flow_id', 'contact_id', 'trigger_key']);
            $table->index(['status', 'next_run_at']);
            $table->index(['contact_id', 'flow_id', 'created_at']);
        });

        Schema::create('automation_enrolment_events', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('enrolment_id');
            $table->string('step_key', 20)->nullable();
            $table->string('event', 20);
            $table->string('reason', 50)->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('enrolment_id')->references('id')->on('automation_enrolments')->cascadeOnDelete();

            $table->index(['enrolment_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('automation_enrolment_events');

        Schema::dropIfExists('automation_enrolments');
    }
};
