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
        DB::statement("ALTER TABLE application_processes MODIFY COLUMN stage ENUM('evaluator', 'document_evaluator', 'grade_evaluator', 'interviewer', 'medical', 'records') NOT NULL");

        DB::table('application_processes')
            ->where('stage', 'evaluator')
            ->update(['stage' => 'document_evaluator']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('application_processes')
            ->whereIn('stage', ['document_evaluator', 'grade_evaluator'])
            ->update(['stage' => 'evaluator']);

        DB::statement("ALTER TABLE application_processes MODIFY COLUMN stage ENUM('evaluator', 'interviewer', 'medical', 'records') NOT NULL");
    }
};
