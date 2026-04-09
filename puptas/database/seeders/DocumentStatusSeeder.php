<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DocumentStatusSeeder extends Seeder
{
    public function run(): void
    {
        $statuses = ['pending', 'uploaded', 'grade review', 'completed'];

        foreach ($statuses as $status) {
            DB::table('document_statuses')->updateOrInsert(
                ['document_status' => $status],
                ['created_at' => now(), 'updated_at' => now()]
            );
        }
    }
}
