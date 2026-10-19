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
        Schema::create('automation_contacts', function (Blueprint $table) {
            $table->increments('id');
            $table->uuid('uuid')->unique();
            $table->unsignedInteger('channel_id');
            $table->unsignedInteger('customer_id')->nullable();
            $table->string('email', 191)->nullable();
            $table->string('phone_e164', 20)->nullable();
            $table->string('phone_raw', 50)->nullable();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('locale', 10);
            $table->char('country', 2)->nullable();
            $table->timestamp('last_order_at')->nullable();
            $table->timestamps();

            $table->foreign('channel_id')->references('id')->on('channels')->cascadeOnDelete();
            $table->foreign('customer_id')->references('id')->on('customers')->cascadeOnDelete();

            $table->unique(['channel_id', 'customer_id']);
            $table->index(['channel_id', 'email']);
            $table->index(['channel_id', 'phone_e164']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('automation_contacts');
    }
};
