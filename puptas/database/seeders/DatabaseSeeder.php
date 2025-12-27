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
        // Run ProgramSeeder first if users depend on programs
        $this->call([
            ProgramSeeder::class,
            UserSeeder::class,
        ]);
    }
}
