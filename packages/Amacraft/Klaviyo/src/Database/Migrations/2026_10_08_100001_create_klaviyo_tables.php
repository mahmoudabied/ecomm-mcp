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
        Schema::create('klaviyo_connections', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('channel_id');
            $table->text('api_key');
            $table->string('public_key')->nullable();
            $table->string('status', 20)->default('disconnected');
            $table->string('last_error')->nullable();
            $table->string('list_id')->nullable();
            $table->string('sms_list_id')->nullable();
            $table->timestamp('consent_cursor')->nullable();
            $table->json('unsubscribed_metric_ids')->nullable();
            $table->timestamp('connected_at')->nullable();
            $table->timestamps();

            $table->foreign('channel_id')->references('id')->on('channels')->cascadeOnDelete();

            $table->unique('channel_id');
        });

        Schema::create('klaviyo_outbox', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('connection_id');
            $table->string('kind', 20);
            $table->string('unique_id', 191);
            $table->json('payload');
            $table->string('status', 20)->default('pending');
            $table->tinyInteger('attempts')->default(0);
            $table->timestamp('available_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->foreign('connection_id')->references('id')->on('klaviyo_connections')->cascadeOnDelete();

            $table->unique(['connection_id', 'kind', 'unique_id']);
            $table->index(['status', 'available_at']);
        });

        Schema::create('klaviyo_sync_jobs', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('connection_id');
            $table->string('entity', 20);
            $table->string('status', 20)->default('queued');
            $table->unsignedInteger('cursor_id')->default(0);
            $table->unsignedInteger('processed')->default(0);
            $table->unsignedInteger('total')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->string('last_error')->nullable();
            $table->timestamps();

            $table->foreign('connection_id')->references('id')->on('klaviyo_connections')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('klaviyo_sync_jobs');

        Schema::dropIfExists('klaviyo_outbox');

        Schema::dropIfExists('klaviyo_connections');
    }
};
