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
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('privacy_consent_at');
            }
            if (!Schema::hasColumn('users', 'deleted_at')) {
                $table->softDeletes()->after('updated_at');
            }
            $table->index(['is_active', 'deleted_at']);
        });

        Schema::table('applicant_profiles', function (Blueprint $table) {
            if (!Schema::hasColumn('applicant_profiles', 'deleted_at')) {
                $table->softDeletes()->after('updated_at');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'is_active') && Schema::hasColumn('users', 'deleted_at')) {
                try {
                    $table->dropIndex(['is_active', 'deleted_at']);
                } catch (\Throwable $e) {
                    // Index may not exist or already dropped
                }
            }
            if (Schema::hasColumn('users', 'is_active')) {
                $table->dropColumn('is_active');
            }
            if (Schema::hasColumn('users', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
        });

        Schema::table('applicant_profiles', function (Blueprint $table) {
            if (Schema::hasColumn('applicant_profiles', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
        });
    }
};
