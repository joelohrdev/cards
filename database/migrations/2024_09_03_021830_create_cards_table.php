<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cards', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->integer('year')->nullable();
            $table->string('mfg')->nullable();
            $table->string('set')->nullable();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('team')->nullable();
            $table->string('numbered')->nullable();
            $table->boolean('rookie')->nullable();
            $table->string('parallel')->nullable();
            $table->bigInteger('value')->nullable();
            $table->text('notes')->nullable();
            $table->string('image_front')->nullable();
            $table->string('image_back')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cards');
    }
};
