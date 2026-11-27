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
        Schema::table('grading_submissions', function (Blueprint $table) {
            $table->string('outbound_carrier')->nullable()->after('submitted_on');
            $table->string('outbound_tracking')->nullable()->after('outbound_carrier');
            $table->date('received_on')->nullable()->after('outbound_tracking');
            $table->date('shipped_back_on')->nullable()->after('received_on');
            $table->string('return_carrier')->nullable()->after('shipped_back_on');
            $table->string('return_tracking')->nullable()->after('return_carrier');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('grading_submissions', function (Blueprint $table) {
            $table->dropColumn(['outbound_carrier', 'outbound_tracking', 'received_on', 'shipped_back_on', 'return_carrier', 'return_tracking']);
        });
    }
};
