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
        Schema::table('sar_generations', function (Blueprint $table) {
            $table->foreignId('created_by_user_id')->nullable()->after('test_passer_id')->constrained('users')->nullOnDelete();
            $table->boolean('email_sent_successfully')->default(false)->after('sent_to_email');
            $table->index('created_by_user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sar_generations', function (Blueprint $table) {
            $table->dropForeign(['created_by_user_id']);
            $table->dropColumn(['created_by_user_id', 'email_sent_successfully']);
        });
    }
};
