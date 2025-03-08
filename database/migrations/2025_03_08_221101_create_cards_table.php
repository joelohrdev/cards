<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('cards', function (Blueprint $table) {
            $table->id();
            $table->uuid();
            $table->foreignId('collection_id');
            $table->string('name');
            $table->string('team');
            $table->string('year')->nullable();
            $table->string('brand')->nullable();
            $table->string('number')->nullable();
            $table->string('set_name')->nullable();
            $table->string('image')->nullable();
            $table->integer('quantity')->nullable();
            $table->integer('estimated_value')->nullable();
            $table->integer('purchase_price')->nullable();
            $table->date('purchase_date')->nullable();
            $table->boolean('is_sold')->default(false);
            $table->date('sold_date')->nullable();
            $table->boolean('favorite')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cards');
    }
};
