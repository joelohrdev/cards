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
        Schema::create('want_list_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('card_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parallel_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('max_price')->nullable();
            $table->unsignedTinyInteger('priority')->default(2);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'card_id', 'parallel_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('want_list_entries');
    }
};
