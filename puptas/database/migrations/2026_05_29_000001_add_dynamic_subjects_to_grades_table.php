<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add dynamic_subjects JSON column to store applicant-added subjects per category.
     *
     * Stores an array of objects: [{ "category": "math", "name": "...", "grade": 92.50 }, ...]
     * Maximum 5 entries per category (15 total). Nullable for backward compatibility.
     */
    public function up(): void
    {
        Schema::table('grades', function (Blueprint $table) {
            $table->json('dynamic_subjects')->nullable()->after('g12_physical_science');
        });
    }

    public function down(): void
    {
        Schema::table('grades', function (Blueprint $table) {
            $table->dropColumn('dynamic_subjects');
        });
    }
};
