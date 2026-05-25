<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Insert the 'waitlisted_below_cutoff' status record with id=4
        // Using insertOrIgnore to prevent duplicate record errors
        DB::table('passer_statuses')->insertOrIgnore([
            [
                'id' => 4,
                'status' => 'waitlisted_below_cutoff',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove the 'waitlisted_below_cutoff' record safely
        DB::table('passer_statuses')
            ->where('id', 4)
            ->where('status', 'waitlisted_below_cutoff')
            ->delete();
    }
};
