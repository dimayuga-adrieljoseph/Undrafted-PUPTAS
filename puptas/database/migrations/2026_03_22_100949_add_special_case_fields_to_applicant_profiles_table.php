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
            $table->string('admission_decision')->default('FAILED')->after('user_id');
            $table->string('applicant_status')->default('FOR_SPECIAL_REVIEW')->after('admission_decision');
            $table->string('source')->default('IDP')->after('applicant_status');
            $table->boolean('is_special_case')->default(false)->after('source');
            $table->text('special_case_reason')->nullable()->after('is_special_case');
            $table->unsignedBigInteger('special_case_approved_by')->nullable()->after('special_case_reason');
            $table->timestamp('special_case_approved_at')->nullable()->after('special_case_approved_by');

            $table->foreign('special_case_approved_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('applicant_profiles', function (Blueprint $table) {
            $table->dropForeign(['special_case_approved_by']);
            $table->dropColumn([
                'admission_decision',
                'applicant_status',
                'source',
                'is_special_case',
                'special_case_reason',
                'special_case_approved_by',
                'special_case_approved_at',
            ]);
        });
    }
};
