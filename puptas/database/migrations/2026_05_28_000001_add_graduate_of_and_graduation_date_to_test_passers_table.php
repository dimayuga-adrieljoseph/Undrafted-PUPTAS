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
        // Uses query builder instead of raw MySQL JOIN syntax for SQLite compatibility (tests)
        $passers = DB::table('test_passers')
            ->whereNotNull('user_id')
            ->where(function ($q) {
                $q->whereNull('graduate_of')->orWhereNull('graduation_date');
            })
            ->get(['test_passer_id', 'user_id']);

        foreach ($passers as $passer) {
            $profile = DB::table('applicant_profiles')
                ->where('user_id', $passer->user_id)
                ->first(['id', 'date_graduated']);

            if (!$profile) continue;

            $graduateType = DB::table('applicant_profile_graduate_type as apgt')
                ->join('graduate_types as gt', 'gt.id', '=', 'apgt.graduate_type_id')
                ->where('apgt.applicant_profile_id', $profile->id)
                ->value('gt.label');

            DB::table('test_passers')->where('test_passer_id', $passer->test_passer_id)->update([
                'graduate_of'     => $graduateType,
                'graduation_date' => $profile->date_graduated,
            ]);
        }
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
