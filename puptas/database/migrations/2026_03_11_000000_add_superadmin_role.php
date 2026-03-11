<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Adds the superadmin role to the roles table.
     */
    public function up(): void
    {
        // Check if superadmin role already exists
        $exists = DB::table('roles')->where('id', 7)->exists();
        
        if (!$exists) {
            DB::table('roles')->insert([
                ['id' => 7, 'name' => 'Superadmin', 'created_at' => now(), 'updated_at' => now()]
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('roles')->where('id', 7)->delete();
    }
};
