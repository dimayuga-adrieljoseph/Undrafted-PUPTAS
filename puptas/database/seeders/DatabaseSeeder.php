<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Seed reference data first, then users (custom users table has no `name` column)
        $this->call([
            ProgramSeeder::class,
            UserSeeder::class,
        ]);
    }
}
