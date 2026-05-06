<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'student_number')) {
                // Safely drop the unique constraint before dropping the column.
                // The constraint name is usually table_column_unique
                $table->dropUnique('users_student_number_unique');
                $table->dropColumn('student_number');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'student_number')) {
                $table->string('student_number', 50)->nullable()->unique()->after('id');
            }
        });
    }
};
