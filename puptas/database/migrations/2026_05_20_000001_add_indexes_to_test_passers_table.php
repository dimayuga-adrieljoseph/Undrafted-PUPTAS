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
        $existingIndexes = collect(Schema::getIndexes('test_passers'))->pluck('name')->toArray();

        Schema::table('test_passers', function (Blueprint $table) use ($existingIndexes) {
            if (!in_array('idx_test_passers_school_year_batch', $existingIndexes)) {
                $table->index(['school_year', 'batch_number'], 'idx_test_passers_school_year_batch');
            }

            if (!in_array('idx_test_passers_strand', $existingIndexes)) {
                $table->index('strand', 'idx_test_passers_strand');
            }

            if (!in_array('idx_test_passers_passer_status_id', $existingIndexes)) {
                $table->index('passer_status_id', 'idx_test_passers_passer_status_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $existingIndexes = collect(Schema::getIndexes('test_passers'))->pluck('name')->toArray();

        Schema::table('test_passers', function (Blueprint $table) use ($existingIndexes) {
            if (in_array('idx_test_passers_school_year_batch', $existingIndexes)) {
                $table->dropIndex('idx_test_passers_school_year_batch');
            }

            if (in_array('idx_test_passers_strand', $existingIndexes)) {
                $table->dropIndex('idx_test_passers_strand');
            }

            if (in_array('idx_test_passers_passer_status_id', $existingIndexes)) {
                $table->dropIndex('idx_test_passers_passer_status_id');
            }
        });
    }
};
