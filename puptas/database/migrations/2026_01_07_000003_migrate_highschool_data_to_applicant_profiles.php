<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Copy existing high school data from users to applicant_profiles
        $applicants = DB::table('users')
            ->where('role_id', 1) // Only applicants
            ->whereNotNull('school') // Only if they have high school data
            ->get();

        foreach ($applicants as $applicant) {
            DB::table('applicant_profiles')->insert([
                'user_id' => $applicant->id,
                'school' => $applicant->school,
                'school_address' => $applicant->school_address,
                'school_year' => $applicant->school_year,
                'date_graduated' => $applicant->date_graduated,
                'strand' => $applicant->strand,
                'track' => $applicant->track,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Drop the columns from users table
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'school',
                'school_address',
                'school_year',
                'date_graduated',
                'strand',
                'track',
            ]);
        });
    }

    public function down(): void
    {
        // Re-add columns to users table
        Schema::table('users', function (Blueprint $table) {
            $table->string('school')->nullable()->after('address');
            $table->string('school_address')->nullable()->after('school');
            $table->string('school_year')->nullable()->after('school_address');
            $table->date('date_graduated')->nullable()->after('school_year');
            $table->string('strand')->nullable()->after('date_graduated');
            $table->string('track')->nullable()->after('strand');
        });

        // Copy data back from applicant_profiles to users
        $profiles = DB::table('applicant_profiles')->get();

        foreach ($profiles as $profile) {
            DB::table('users')
                ->where('id', $profile->user_id)
                ->update([
                    'school' => $profile->school,
                    'school_address' => $profile->school_address,
                    'school_year' => $profile->school_year,
                    'date_graduated' => $profile->date_graduated,
                    'strand' => $profile->strand,
                    'track' => $profile->track,
                ]);
        }
    }
};
