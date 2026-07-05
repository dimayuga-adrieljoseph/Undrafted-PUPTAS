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
        DB::table('passer_statuses')->insertOrIgnore([
            [
                'id' => 5,
                'status' => 'on_probation',
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
        DB::table('passer_statuses')
            ->where('id', 5)
            ->where('status', 'on_probation')
            ->delete();
    }
};
