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
        Schema::create('automation_consents', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('contact_id');
            $table->string('channel', 20);
            $table->string('status', 20);
            $table->string('source', 50);
            $table->timestamp('changed_at');
            $table->timestamps();

            $table->foreign('contact_id')->references('id')->on('automation_contacts')->cascadeOnDelete();

            $table->unique(['contact_id', 'channel']);
        });

        Schema::create('automation_consent_events', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('contact_id');
            $table->string('channel', 20);
            $table->string('status', 20);
            $table->string('source', 50);
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('contact_id')->references('id')->on('automation_contacts')->cascadeOnDelete();

            $table->index(['contact_id', 'channel', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('automation_consent_events');

        Schema::dropIfExists('automation_consents');
    }
};
