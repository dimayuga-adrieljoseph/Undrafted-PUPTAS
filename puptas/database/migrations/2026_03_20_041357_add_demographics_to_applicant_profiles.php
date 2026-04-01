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
        Schema::table('applicant_profiles', function (Blueprint $table) {
            // Identity fields from IDP and User input
            $table->string('email')->nullable()->after('user_id');
            $table->string('student_number')->nullable()->after('email');

            // Demographic fields migrated from old `users` table
            $table->string('salutation', 10)->nullable()->after('student_number');
            $table->string('firstname')->nullable()->after('salutation');
            $table->string('middlename')->nullable()->after('firstname');
            $table->string('extension_name')->nullable()->after('middlename');
            $table->string('lastname')->nullable()->after('extension_name');
            $table->date('birthday')->nullable()->after('lastname');
            $table->string('sex')->nullable()->after('birthday');
            $table->string('contactnumber')->nullable()->after('sex');

            // Normalized address fields
            $table->string('street_address')->nullable()->after('contactnumber');
            $table->string('barangay')->nullable()->after('street_address');
            $table->string('city')->nullable()->after('barangay');
            $table->string('province')->nullable()->after('city');
            $table->string('postal_code')->nullable()->after('province');

            // Privacy consent compliance
            $table->boolean('privacy_consent')->default(false)->after('postal_code');
            $table->timestamp('privacy_consent_at')->nullable()->after('privacy_consent');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('applicant_profiles', function (Blueprint $table) {
            $table->dropColumn([
                'email',
                'student_number',
                'salutation',
                'firstname',
                'middlename',
                'extension_name',
                'lastname',
                'birthday',
                'sex',
                'contactnumber',
                'street_address',
                'barangay',
                'city',
                'province',
                'postal_code',
                'privacy_consent',
                'privacy_consent_at',
            ]);
        });
    }
};
