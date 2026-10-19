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
        Schema::create('automation_abandoned_carts', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('cart_id');
            $table->unsignedInteger('contact_id');
            $table->unsignedInteger('channel_id');
            $table->timestamp('detected_at');
            $table->string('status', 20)->default('abandoned');
            $table->char('restore_token_hash', 64)->unique();
            $table->timestamp('restore_token_expires_at');
            $table->unsignedInteger('restored_cart_id')->nullable();
            $table->unsignedInteger('recovered_order_id')->nullable();
            $table->json('cart_snapshot')->nullable();
            $table->timestamps();

            $table->foreign('cart_id')->references('id')->on('cart')->cascadeOnDelete();
            $table->foreign('contact_id')->references('id')->on('automation_contacts')->cascadeOnDelete();
            $table->foreign('channel_id')->references('id')->on('channels')->cascadeOnDelete();
            $table->foreign('restored_cart_id')->references('id')->on('cart')->nullOnDelete();
            $table->foreign('recovered_order_id')->references('id')->on('orders')->nullOnDelete();

            $table->unique('cart_id');
        });

        Schema::create('automation_back_in_stock_subscriptions', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('contact_id');
            $table->unsignedInteger('product_id');
            $table->unsignedInteger('channel_id');
            $table->string('status', 20)->default('waiting');
            $table->timestamp('notified_at')->nullable();
            $table->timestamps();

            $table->foreign('contact_id')->references('id')->on('automation_contacts')->cascadeOnDelete();
            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
            $table->foreign('channel_id')->references('id')->on('channels')->cascadeOnDelete();

            $table->unique(['contact_id', 'product_id', 'channel_id'], 'automation_biss_contact_product_channel_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('automation_back_in_stock_subscriptions');

        Schema::dropIfExists('automation_abandoned_carts');
    }
};
