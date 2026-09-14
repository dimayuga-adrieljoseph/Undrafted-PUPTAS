<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add an index on applicant_profiles.user_id.
 *
 * This column is a string UUID (IDP identity) with no FK constraint by design,
 * but it is the primary filter in nearly every domain query — stage lookups,
 * file lookups, grade lookups, and application joins all filter on it.
 * Without an index every one of those queries is a full table scan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('applicant_profiles', function (Blueprint $table) {
            // Guard against re-running on a DB that already has the index.
            $existing = collect(Schema::getIndexes('applicant_profiles'))
                ->pluck('name')
                ->toArray();

            if (! in_array('applicant_profiles_user_id_index', $existing, true)) {
                $table->index('user_id', 'applicant_profiles_user_id_index');
            }
        });
    }

    public function down(): void
    {
        Schema::table('applicant_profiles', function (Blueprint $table) {
            $table->dropIndex('applicant_profiles_user_id_index');
        });
    }
};
