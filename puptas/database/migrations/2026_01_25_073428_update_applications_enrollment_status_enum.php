<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Check if the table and column exist before modifying
        if (Schema::hasTable('applications') && Schema::hasColumn('applications', 'enrollment_status')) {
            DB::statement("ALTER TABLE applications MODIFY COLUMN enrollment_status ENUM('pending', 'temporary', 'officially_enrolled') DEFAULT 'pending'");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('applications') && Schema::hasColumn('applications', 'enrollment_status')) {
            DB::statement("ALTER TABLE applications MODIFY COLUMN enrollment_status ENUM('pending', 'enrolled', 'rejected', 'waitlist') DEFAULT 'pending'");
        }
    }
};
