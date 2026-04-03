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

        // Deterministic mapping for legacy free-text values:
        // Any value that looks like a year range (e.g. "2023-2024", "2023/2024", bare "2024")
        // or is otherwise unrecognised maps to "Senior High School of Past School Years".
        // The exact current AY label is only matched when the stored value is already that label.
        $currentAyLabel  = 'Senior High School of A.Y. 2025-2026';
        $pastYearsLabel  = 'Senior High School of Past School Years';
        $alsLabel        = 'Alternative Learning System';

        $resolveTypeId = function (string $raw) use ($typeMap, $currentAyLabel, $pastYearsLabel, $alsLabel): int {
            // Exact match first (covers already-normalised rows)
            if (isset($typeMap[$raw])) {
                return $typeMap[$raw];
            }

            // ALS variants
            if (preg_match('/\bals\b|alternative\s+learning/i', $raw)) {
                return $typeMap[$alsLabel];
            }

            // Current AY: only if the stored value explicitly mentions 2025-2026
            if (preg_match('/2025[\-\/]2026/', $raw)) {
                return $typeMap[$currentAyLabel];
            }

            // Everything else (year ranges, bare years, unrecognised text) → past years
            return $typeMap[$pastYearsLabel];
        };

        $unmapped = [];

        DB::table('applicant_profiles')
            ->whereNotNull('school_year')
            ->orderBy('id')
            ->chunk(500, function ($profiles) use ($resolveTypeId, &$unmapped) {
                $rows = [];
                $ts   = now();

                foreach ($profiles as $profile) {
                    $raw    = trim($profile->school_year);
                    $typeId = $resolveTypeId($raw);

                    // Track rows that needed a fallback for audit purposes
                    if (!in_array($raw, [
                        'Senior High School of A.Y. 2025-2026',
                        'Senior High School of Past School Years',
                        'Alternative Learning System',
                    ], true)) {
                        $unmapped[] = ['id' => $profile->id, 'raw_school_year' => $raw];
                    }

                    $rows[] = [
                        'applicant_profile_id' => $profile->id,
                        'graduate_type_id'     => $typeId,
                        'created_at'           => $ts,
                        'updated_at'           => $ts,
                    ];
                }

                if (!empty($rows)) {
                    DB::table('applicant_profile_graduate_type')->insertOrIgnore($rows);
                }
            });

        // Log unmapped rows so they can be reviewed after the migration
        if (!empty($unmapped)) {
            \Illuminate\Support\Facades\Log::warning(
                'graduate_types migration: ' . count($unmapped) . ' row(s) had unrecognised school_year values and were mapped to "Senior High School of Past School Years".',
                ['rows' => $unmapped]
            );
        }

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
