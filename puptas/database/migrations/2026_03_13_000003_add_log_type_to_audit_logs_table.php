<?php

use App\Models\AuditLog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->string('log_type', 20)->nullable()->after('user_role');
            $table->index('log_type');
        });

        DB::table('audit_logs')
            ->where(function ($query) {
                $query->whereIn('action_type', [AuditLog::ACTION_LOGIN, AuditLog::ACTION_LOGOUT])
                    ->orWhere('log_category', AuditLog::CATEGORY_AUTHENTICATION)
                    ->orWhere('module_name', 'Authentication');
            })
            ->update(['log_type' => AuditLog::TYPE_SECURITY]);

        DB::table('audit_logs')
            ->whereNull('log_type')
            ->where('log_category', AuditLog::CATEGORY_SYSTEM_OPERATION)
            ->update(['log_type' => AuditLog::TYPE_SYSTEM]);

        DB::table('audit_logs')
            ->whereNull('log_type')
            ->update(['log_type' => AuditLog::TYPE_AUDIT]);
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropIndex(['log_type']);
            $table->dropColumn('log_type');
        });
    }
};
