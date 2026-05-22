<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bulk_email_operations', function (Blueprint $table) {
            $table->index(['status', 'started_at'], 'bulk_email_ops_status_started_at_index');
            $table->index('initiated_by', 'bulk_email_ops_initiated_by_index');
        });
    }

    public function down(): void
    {
        Schema::table('bulk_email_operations', function (Blueprint $table) {
            $table->dropIndex('bulk_email_ops_status_started_at_index');
            $table->dropIndex('bulk_email_ops_initiated_by_index');
        });
    }
};
