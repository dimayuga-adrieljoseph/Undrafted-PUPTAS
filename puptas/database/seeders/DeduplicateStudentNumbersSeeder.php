<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Identifies and resolves duplicate student numbers in applicant_profiles
 * before the unique-constraint migration can run.
 *
 * Strategy: for each group of duplicates, the record with the oldest
 * `created_at` keeps its number; all newer duplicates get NULLed out so
 * a fresh unique number can be assigned later via StudentNumberService.
 *
 * Run with:
 *   php artisan db:seed --class=DeduplicateStudentNumbersSeeder
 */
class DeduplicateStudentNumbersSeeder extends Seeder
{
    public function run(): void
    {
        if (!\Illuminate\Support\Facades\Schema::hasColumn('applicant_profiles', 'student_number')) {
            $this->command->info('✅ Column student_number does not exist yet. Nothing to do.');
            return;
        }

        $duplicates = DB::table('applicant_profiles')
            ->select('student_number', DB::raw('COUNT(*) as cnt'))
            ->whereNotNull('student_number')
            ->groupBy('student_number')
            ->having('cnt', '>', 1)
            ->get();

        if ($duplicates->isEmpty()) {
            $this->command->info('✅ No duplicate student numbers found. Nothing to do.');
            return;
        }

        $this->command->warn("⚠️  Found {$duplicates->count()} duplicate student number(s):");

        foreach ($duplicates as $dup) {
            $records = DB::table('applicant_profiles')
                ->where('student_number', $dup->student_number)
                ->orderBy('created_at')   // oldest record keeps the number
                ->orderBy('id')
                ->get(['id', 'user_id', 'student_number', 'created_at']);

            $this->command->line("  • {$dup->student_number} ({$dup->cnt} records)");

            $keeper = $records->shift();
            $this->command->line("    ✔ Keeping   id={$keeper->id} (user_id={$keeper->user_id}, created={$keeper->created_at})");

            foreach ($records as $dupe) {
                DB::table('applicant_profiles')
                    ->where('id', $dupe->id)
                    ->update(['student_number' => null, 'updated_at' => now()]);

                $this->command->line("    ✖ Nulled    id={$dupe->id} (user_id={$dupe->user_id}, created={$dupe->created_at})");
            }
        }

        $this->command->info('✅ Deduplication complete. You can now run: php artisan migrate');
    }
}
