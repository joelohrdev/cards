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
        Schema::create('card_sets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('manufacturer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('sport')->index();
            $table->unsignedSmallInteger('year')->index();
            $table->string('season')->nullable();
            $table->string('name');
            $table->unsignedInteger('card_count')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['year', 'manufacturer_id', 'name', 'sport']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('card_sets');
    }
};
