<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('applicant_profile_document_status', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('applicant_profile_id');
            $table->unsignedBigInteger('document_status_id');
            $table->timestamps();

            $table->foreign('applicant_profile_id')
                ->references('id')
                ->on('applicant_profiles')
                ->onDelete('cascade');

            $table->foreign('document_status_id')
                ->references('id')
                ->on('document_statuses')
                ->onDelete('cascade');

            $table->unique(['applicant_profile_id', 'document_status_id'], 'apds_profile_status_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('applicant_profile_document_status');
    }
};
