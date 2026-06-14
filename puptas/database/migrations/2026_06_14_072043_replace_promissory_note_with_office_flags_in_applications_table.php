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
        Schema::table('applications', function (Blueprint $table) {
            $table->dropColumn('requires_promissory_note');
            $table->boolean('requires_guidance_office')->default(false)->after('enrollment_position');
            $table->boolean('requires_admission_office')->default(false)->after('requires_guidance_office');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->dropColumn('requires_guidance_office');
            $table->dropColumn('requires_admission_office');
            $table->boolean('requires_promissory_note')->default(false)->after('enrollment_position');
        });
    }
};
