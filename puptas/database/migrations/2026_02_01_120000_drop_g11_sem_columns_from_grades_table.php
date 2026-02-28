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
        // Only drop columns if they exist
        $columnsToDrop = [];
        if (Schema::hasColumn('grades', 'g11_first_sem')) {
            $columnsToDrop[] = 'g11_first_sem';
        }
        if (Schema::hasColumn('grades', 'g11_second_sem')) {
            $columnsToDrop[] = 'g11_second_sem';
        }

        if (!empty($columnsToDrop)) {
            Schema::table('grades', function (Blueprint $table) use ($columnsToDrop) {
                $table->dropColumn($columnsToDrop);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('grades', function (Blueprint $table) {
            $table->decimal('g11_first_sem', 5, 2)->nullable();
            $table->decimal('g11_second_sem', 5, 2)->nullable();
        });
    }
};
