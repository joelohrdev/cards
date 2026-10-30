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
        Schema::create('ebay_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('ebay_user_id')->nullable()->index();
            $table->string('username')->nullable();
            $table->text('access_token');
            $table->timestamp('access_token_expires_at');
            $table->text('refresh_token');
            $table->timestamp('refresh_token_expires_at')->nullable();
            $table->string('fulfillment_policy_id')->nullable();
            $table->string('payment_policy_id')->nullable();
            $table->string('return_policy_id')->nullable();
            $table->string('merchant_location_key')->nullable();
            $table->timestamp('orders_synced_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ebay_accounts');
    }
};
