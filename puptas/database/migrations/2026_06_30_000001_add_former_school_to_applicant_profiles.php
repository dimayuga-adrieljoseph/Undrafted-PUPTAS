<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds Former School Information fields to applicant_profiles
     * for the F137 Request Letter feature.
     *
     * School name is sourced from the existing `school` field — no new column needed.
     * - former_school_address   (required for F137 generation)
     * - former_school_principal (optional — used as principal/registrar addressee)
     */
    public function up(): void
    {
        Schema::table('applicant_profiles', function (Blueprint $table) {
            $table->string('former_school_address')->nullable()->after('school');
            $table->string('former_school_principal')->nullable()->after('former_school_address');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('applicant_profiles', function (Blueprint $table) {
            $table->dropColumn([
                'former_school_address',
                'former_school_principal',
            ]);
        });
    }
};
