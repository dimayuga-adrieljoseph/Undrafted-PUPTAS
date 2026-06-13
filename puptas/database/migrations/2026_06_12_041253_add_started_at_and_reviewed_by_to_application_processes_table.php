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
        Schema::table('application_processes', function (Blueprint $table) {
            $table->timestamp('started_at')->nullable()->after('created_at');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete()->after('started_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('application_processes', function (Blueprint $table) {
            $table->dropForeign(['reviewed_by']);
            $table->dropColumn(['started_at', 'reviewed_by']);
        });
    }
};
