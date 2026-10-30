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
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('collection_item_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('platform');
            $table->date('sold_on');
            $table->unsignedInteger('sale_price');
            $table->unsignedInteger('fees')->default(0);
            $table->unsignedInteger('shipping_cost')->default(0);
            $table->string('buyer')->nullable();
            $table->string('external_order_id')->nullable()->index();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'sold_on']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sales');
    }
};
