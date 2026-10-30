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
        Schema::create('grading_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('grader');
            $table->string('service_level')->nullable();
            $table->string('reference')->nullable();
            $table->string('status')->default('preparing');
            $table->date('submitted_on')->nullable();
            $table->date('returned_on')->nullable();
            $table->unsignedInteger('shipping_cost')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('grading_submission_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grading_submission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('collection_item_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('declared_value')->nullable();
            $table->unsignedInteger('fee')->default(0);
            $table->string('resulting_grade')->nullable();
            $table->string('resulting_cert_number')->nullable();
            $table->timestamps();

            $table->unique(['grading_submission_id', 'collection_item_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('grading_submission_items');
        Schema::dropIfExists('grading_submissions');
    }
};
