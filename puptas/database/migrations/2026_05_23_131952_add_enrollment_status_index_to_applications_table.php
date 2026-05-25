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
        Schema::table('applications', function (Blueprint $table) {
            $existingIndexes = collect(Schema::getIndexes('applications'))->pluck('name')->toArray();
            if (!in_array('applications_enrollment_status_index', $existingIndexes)) {
                $table->index('enrollment_status', 'applications_enrollment_status_index');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $existingIndexes = collect(Schema::getIndexes('applications'))->pluck('name')->toArray();
            if (in_array('applications_enrollment_status_index', $existingIndexes)) {
                $table->dropIndex('applications_enrollment_status_index');
            }
        });
    }
};
