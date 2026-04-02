<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Create graduate_types lookup table
        Schema::create('graduate_types', function (Blueprint $table) {
            $table->id();
            $table->string('label')->unique();
            $table->timestamps();
        });

        // Seed the three options
        DB::table('graduate_types')->insert([
            ['label' => 'Senior High School of A.Y. 2025-2026', 'created_at' => now(), 'updated_at' => now()],
            ['label' => 'Senior High School of Past School Years', 'created_at' => now(), 'updated_at' => now()],
            ['label' => 'Alternative Learning System', 'created_at' => now(), 'updated_at' => now()],
        ]);

        // 2. Create junction table
        Schema::create('applicant_profile_graduate_type', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('applicant_profile_id');
            $table->unsignedBigInteger('graduate_type_id');
            $table->timestamps();

            $table->foreign('applicant_profile_id')
                ->references('id')->on('applicant_profiles')
                ->onDelete('cascade');

            $table->foreign('graduate_type_id')
                ->references('id')->on('graduate_types')
                ->onDelete('cascade');

            $table->unique(
                ['applicant_profile_id', 'graduate_type_id'],
                'ap_graduate_type_unique'
            );
        });

        // 3. Migrate existing school_year data into junction table
        $typeMap = DB::table('graduate_types')->pluck('id', 'label');

        DB::table('applicant_profiles')
            ->whereNotNull('school_year')
            ->get(['id', 'school_year'])
            ->each(function ($profile) use ($typeMap) {
                $typeId = $typeMap[$profile->school_year] ?? null;
                if ($typeId) {
                    DB::table('applicant_profile_graduate_type')->insert([
                        'applicant_profile_id' => $profile->id,
                        'graduate_type_id'     => $typeId,
                        'created_at'           => now(),
                        'updated_at'           => now(),
                    ]);
                }
            });

        // 4. Drop school_year column from applicant_profiles
        Schema::table('applicant_profiles', function (Blueprint $table) {
            $table->dropColumn('school_year');
        });
    }

    public function down(): void
    {
        Schema::table('applicant_profiles', function (Blueprint $table) {
            $table->string('school_year')->nullable();
        });

        Schema::dropIfExists('applicant_profile_graduate_type');
        Schema::dropIfExists('graduate_types');
    }
};
