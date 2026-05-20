<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add the missing g12_physical_science column for HUMSS strand.
     *
     * The previous migration (2026_05_18_203259) added g12_earth_life_science
     * as the HUMSS G12 science column but omitted g12_physical_science, which
     * is the actual G12 science subject for HUMSS and is referenced by both
     * the HUMSSGradeInput.vue form and the storeHumssGrades() controller.
     */
    public function up(): void
    {
        Schema::table('grades', function (Blueprint $table) {
            $table->decimal('g12_physical_science', 5, 2)->nullable()->after('g12_earth_life_science');
        });
    }

    public function down(): void
    {
        Schema::table('grades', function (Blueprint $table) {
            $table->dropColumn('g12_physical_science');
        });
    }
};
