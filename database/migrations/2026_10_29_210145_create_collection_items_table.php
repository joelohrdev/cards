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
        Schema::create('collection_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('card_id')->constrained()->restrictOnDelete();
            $table->foreignId('parallel_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('serial_number')->nullable();
            $table->boolean('is_graded')->default(false);
            $table->string('grader')->nullable();
            $table->string('grade')->nullable();
            $table->string('cert_number')->nullable()->index();
            $table->string('raw_condition')->nullable();
            $table->string('status')->default('owned');
            $table->unsignedInteger('purchase_price')->nullable();
            $table->date('purchase_date')->nullable();
            $table->string('purchase_source')->nullable();
            $table->unsignedInteger('estimated_value')->nullable();
            $table->timestamp('value_updated_at')->nullable();
            $table->foreignId('storage_location_id')->nullable()->constrained()->nullOnDelete();
            $table->string('storage_position')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['user_id', 'card_id']);
        });

        Schema::create('collection_item_tag', function (Blueprint $table) {
            $table->foreignId('collection_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();

            $table->primary(['collection_item_id', 'tag_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('collection_item_tag');
        Schema::dropIfExists('collection_items');
    }
};
