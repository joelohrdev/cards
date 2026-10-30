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
        Schema::create('cards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('card_set_id')->constrained()->cascadeOnDelete();
            $table->string('number');
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('name');
            $table->string('subset')->nullable();
            $table->foreignId('team_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('is_rookie')->default(false);
            $table->boolean('is_autograph')->default(false);
            $table->boolean('is_memorabilia')->default(false);
            $table->boolean('is_short_print')->default(false);
            $table->unsignedInteger('print_run')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['card_set_id', 'number']);
            $table->index(['card_set_id', 'sort_order']);
        });

        Schema::create('card_player', function (Blueprint $table) {
            $table->foreignId('card_id')->constrained()->cascadeOnDelete();
            $table->foreignId('player_id')->constrained()->cascadeOnDelete();

            $table->primary(['card_id', 'player_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('card_player');
        Schema::dropIfExists('cards');
    }
};
