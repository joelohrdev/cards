<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Track how far through the file an import has got (so a retried job resumes rather
     * than duplicating rows) and which tag marks the cards it created.
     */
    public function up(): void
    {
        Schema::table('imports', function (Blueprint $table) {
            $table->unsignedInteger('processed_rows')->default(0)->after('total_rows');
            $table->foreignId('tag_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('imports', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tag_id');
            $table->dropColumn('processed_rows');
        });
    }
};
