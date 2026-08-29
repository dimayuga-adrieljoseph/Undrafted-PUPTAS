<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Add a FULLTEXT index covering the name + email columns of applicant_profiles.
 *
 * The existing LIKE '%term%' search on firstname / lastname / email is a
 * leading-wildcard scan that cannot use a B-tree index and degrades linearly
 * with table size.  A FULLTEXT index lets MySQL use full-text search (MATCH …
 * AGAINST) instead, which is index-backed and scales much better.
 *
 * Note: FULLTEXT requires MyISAM or InnoDB ≥ 5.6 (default on modern MySQL/MariaDB).
 */
return new class extends Migration
{
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();
        if (!in_array($driver, ['mysql', 'mariadb'])) {
            return;
        }

        // Schema::table fullText() helper is available in Laravel 9+.
        Schema::table('applicant_profiles', function (Blueprint $table) {
            $existing = collect(Schema::getIndexes('applicant_profiles'))
                ->pluck('name')
                ->toArray();

            if (! in_array('applicant_profiles_name_email_fulltext', $existing, true)) {
                $table->fullText(
                    ['firstname', 'lastname', 'email'],
                    'applicant_profiles_name_email_fulltext'
                );
            }
        });
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();
        if (!in_array($driver, ['mysql', 'mariadb'])) {
            return;
        }

        Schema::table('applicant_profiles', function (Blueprint $table) {
            $table->dropFullText('applicant_profiles_name_email_fulltext');
        });
    }
};
