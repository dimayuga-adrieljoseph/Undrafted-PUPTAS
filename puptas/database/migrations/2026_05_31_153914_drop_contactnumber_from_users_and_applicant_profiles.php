<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'contactnumber')) {
                $table->dropColumn('contactnumber');
            }
        });

        Schema::table('applicant_profiles', function (Blueprint $table) {
            if (Schema::hasColumn('applicant_profiles', 'contactnumber')) {
                $table->dropColumn('contactnumber');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('contactnumber')->nullable()->after('sex');
        });

        Schema::table('applicant_profiles', function (Blueprint $table) {
            $table->string('contactnumber')->nullable()->after('sex');
        });
    }
};
