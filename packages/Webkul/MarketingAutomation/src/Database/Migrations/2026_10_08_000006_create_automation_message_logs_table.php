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
        Schema::create('automation_message_logs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('enrolment_id')->nullable();
            $table->unsignedInteger('flow_id')->nullable();
            $table->string('step_key', 20)->nullable();
            $table->unsignedInteger('contact_id')->nullable();
            $table->unsignedInteger('channel_id');
            $table->string('message_channel', 20);
            $table->boolean('is_marketing')->default(1);
            $table->boolean('is_test')->default(0);
            $table->boolean('is_fallback')->default(0);
            $table->unsignedInteger('provider_connection_id')->nullable();
            $table->string('recipient', 191);
            $table->string('locale', 10);
            $table->string('subject')->nullable();
            $table->mediumText('rendered_body')->nullable();
            $table->string('coupon_code')->nullable();
            $table->json('links')->nullable();
            $table->string('status', 20)->default('queued');
            $table->string('provider_message_id', 191)->nullable();
            $table->string('error_code')->nullable();
            $table->string('error_message')->nullable();
            $table->tinyInteger('attempts')->default(0);
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('clicked_at')->nullable();
            $table->timestamps();

            $table->foreign('enrolment_id')->references('id')->on('automation_enrolments')->nullOnDelete();
            $table->foreign('contact_id')->references('id')->on('automation_contacts')->nullOnDelete();
            $table->foreign('channel_id')->references('id')->on('channels')->cascadeOnDelete();
            $table->foreign('provider_connection_id')->references('id')->on('automation_provider_connections')->nullOnDelete();

            $table->index(['contact_id', 'message_channel', 'is_marketing', 'created_at'], 'automation_message_logs_cap_index');
            $table->index(['flow_id', 'step_key', 'created_at'], 'automation_message_logs_step_index');
            $table->index(['provider_connection_id', 'provider_message_id'], 'automation_message_logs_provider_index');
        });

        Schema::create('automation_message_events', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('message_log_id');
            $table->string('type', 20);
            $table->string('url')->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('message_log_id')->references('id')->on('automation_message_logs')->cascadeOnDelete();

            $table->index(['message_log_id', 'type']);
        });

        Schema::table('automation_enrolment_events', function (Blueprint $table) {
            $table->unsignedBigInteger('message_log_id')->nullable()->after('reason');

            $table->foreign('message_log_id')->references('id')->on('automation_message_logs')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('automation_enrolment_events', function (Blueprint $table) {
            $table->dropForeign(['message_log_id']);

            $table->dropColumn('message_log_id');
        });

        Schema::dropIfExists('automation_message_events');

        Schema::dropIfExists('automation_message_logs');
    }
};
