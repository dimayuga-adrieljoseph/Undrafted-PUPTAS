<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Add 'uploading' and 'failed' to the user_files status enum.
     * This makes the backend authoritative for upload state tracking,
     * replacing the frontend localStorage-based approach.
     */
    public function up(): void
    {
        // MySQL requires ALTER to change enum values.
        // SQLite (used in tests) stores strings and doesn't enforce enums — no-op is safe.
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE user_files MODIFY COLUMN status ENUM('uploading', 'pending', 'approved', 'returned', 'failed') DEFAULT 'pending'");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // First clean up any rows with the new statuses
        DB::table('user_files')->where('status', 'uploading')->update(['status' => 'pending']);
        DB::table('user_files')->where('status', 'failed')->update(['status' => 'pending']);

        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE user_files MODIFY COLUMN status ENUM('pending', 'approved', 'returned') DEFAULT 'pending'");
        }
    }
};
