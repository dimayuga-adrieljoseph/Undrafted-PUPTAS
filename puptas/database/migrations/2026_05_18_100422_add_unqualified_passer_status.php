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
        // Insert the 'unqualified' status record with id=3
        // Using insertOrIgnore to prevent duplicate record errors
        DB::table('passer_statuses')->insertOrIgnore([
            [
                'id' => 3,
                'status' => 'unqualified',
                'created_at' => now(),
                'updated_at' => now()
            ]
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove the 'unqualified' record safely
        DB::table('passer_statuses')
            ->where('id', 3)
            ->where('status', 'unqualified')
            ->delete();
    }
};
