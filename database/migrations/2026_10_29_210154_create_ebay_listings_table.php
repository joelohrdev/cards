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
        Schema::create('ebay_listings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('collection_item_id')->constrained()->cascadeOnDelete();
            $table->string('sku')->unique();
            $table->string('offer_id')->nullable();
            $table->string('listing_id')->nullable()->index();
            $table->string('title', 80);
            $table->text('description');
            $table->unsignedInteger('price');
            $table->string('status')->default('draft');
            $table->text('error')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ebay_listings');
    }
};
