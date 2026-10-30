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
        Schema::create('price_comps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('card_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parallel_id')->nullable()->constrained()->nullOnDelete();
            $table->string('grader')->nullable();
            $table->string('grade')->nullable();
            $table->unsignedInteger('price');
            $table->date('sold_on');
            $table->string('source')->nullable();
            $table->string('url', 2048)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['card_id', 'sold_on']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('price_comps');
    }
};
