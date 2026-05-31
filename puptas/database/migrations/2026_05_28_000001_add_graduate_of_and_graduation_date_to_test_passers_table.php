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
        Schema::table('test_passers', function (Blueprint $table) {
            $table->string('graduate_of')->nullable()->after('shs_school');
            $table->date('graduation_date')->nullable()->after('graduate_of');
        });

        // Backfill existing test passers from their linked applicant profiles
        // graduate_of comes from graduate_types via the junction table
        // graduation_date comes from applicant_profiles.date_graduated
        DB::statement("
            UPDATE test_passers tp
            JOIN applicant_profiles ap ON ap.user_id = tp.user_id
            LEFT JOIN applicant_profile_graduate_type apgt ON apgt.applicant_profile_id = ap.id
            LEFT JOIN graduate_types gt ON gt.id = apgt.graduate_type_id
            SET
                tp.graduate_of = gt.label,
                tp.graduation_date = ap.date_graduated
            WHERE tp.user_id IS NOT NULL
              AND (tp.graduate_of IS NULL OR tp.graduation_date IS NULL)
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('test_passers', function (Blueprint $table) {
            $table->dropColumn(['graduate_of', 'graduation_date']);
        });
    }
};
