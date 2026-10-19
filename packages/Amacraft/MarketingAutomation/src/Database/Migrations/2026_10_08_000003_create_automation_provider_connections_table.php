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
        Schema::create('automation_provider_connections', function (Blueprint $table) {
            $table->increments('id');
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->string('message_channel', 20);
            $table->string('driver', 30);
            $table->text('credentials');
            $table->string('sender', 50);
            $table->string('webhook_secret', 64);
            $table->string('status', 20)->default('untested');
            $table->string('last_error')->nullable();
            $table->timestamp('last_tested_at')->nullable();
            $table->timestamps();
        });

        Schema::create('automation_provider_channel_defaults', function (Blueprint $table) {
            $table->unsignedInteger('channel_id');
            $table->string('message_channel', 20);
            $table->unsignedInteger('provider_connection_id');
            $table->timestamps();

            $table->foreign('channel_id', 'automation_provider_defaults_channel_fk')->references('id')->on('channels')->cascadeOnDelete();
            $table->foreign('provider_connection_id', 'automation_provider_defaults_connection_fk')->references('id')->on('automation_provider_connections')->cascadeOnDelete();

            $table->primary(['channel_id', 'message_channel'], 'automation_provider_defaults_primary');
        });

        Schema::create('automation_whatsapp_templates', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('provider_connection_id');
            $table->string('external_id');
            $table->string('name');
            $table->string('language', 10);
            $table->string('category', 20);
            $table->string('status', 20);
            $table->json('components')->nullable();
            $table->tinyInteger('variable_count')->default(0);
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->foreign('provider_connection_id')->references('id')->on('automation_provider_connections')->cascadeOnDelete();

            $table->unique(['provider_connection_id', 'name', 'language'], 'automation_whatsapp_templates_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('automation_whatsapp_templates');

        Schema::dropIfExists('automation_provider_channel_defaults');

        Schema::dropIfExists('automation_provider_connections');
    }
};
