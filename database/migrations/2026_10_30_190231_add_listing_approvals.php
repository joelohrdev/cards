<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A listing goes to eBay only after everyone other than the person who asked has approved it.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('ebay_listings', function (Blueprint $table) {
            $table->foreignId('approval_requested_by')->nullable()->after('error')->constrained('users')->nullOnDelete();
            $table->timestamp('approval_requested_at')->nullable()->after('approval_requested_by');
            $table->timestamp('approved_at')->nullable()->after('approval_requested_at');
        });

        Schema::create('listing_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ebay_listing_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('decision')->default('pending');
            $table->text('note')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();

            $table->unique(['ebay_listing_id', 'user_id']);
            $table->index(['user_id', 'decision']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('listing_approvals');

        Schema::table('ebay_listings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('approval_requested_by');
            $table->dropColumn(['approval_requested_at', 'approved_at']);
        });
    }
};
