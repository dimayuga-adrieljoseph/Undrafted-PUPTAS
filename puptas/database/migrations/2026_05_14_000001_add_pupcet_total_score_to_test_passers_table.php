<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add pupcet_total_score to test_passers to enable automatic ranking.
     * An index on the column (DESC) ensures ORDER BY pupcet_total_score DESC
     * stays fast even as the table grows.
     */
    public function up(): void
    {
        Schema::table('test_passers', function (Blueprint $table) {
            $table->decimal('pupcet_total_score', 6, 2)
                  ->nullable()
                  ->after('school_year')
                  ->comment('PUPCET total score used for applicant ranking');

            // Index for efficient ORDER BY pupcet_total_score DESC queries
            $table->index('pupcet_total_score', 'test_passers_score_idx');
        });
    }

    public function down(): void
    {
        Schema::table('test_passers', function (Blueprint $table) {
            $table->dropIndex('test_passers_score_idx');
            $table->dropColumn('pupcet_total_score');
        });
    }
};
