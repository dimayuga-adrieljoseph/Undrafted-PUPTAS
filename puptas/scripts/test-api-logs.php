<?php

/**
 * Quick script to check if API logs exist in the database
 * Run with: php test-api-logs.php
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\AuditLog;

echo "=== Audit Log Analysis ===\n\n";

$totalLogs = AuditLog::count();
echo "Total audit logs: {$totalLogs}\n";

$apiLogs = AuditLog::whereNull('user_id')->count();
echo "API/System logs (user_id is null): {$apiLogs}\n";

$userLogs = AuditLog::whereNotNull('user_id')->count();
echo "User logs (user_id is not null): {$userLogs}\n\n";

echo "=== Recent API/System Logs (last 10) ===\n";
$recentApiLogs = AuditLog::whereNull('user_id')
    ->latest()
    ->take(10)
    ->get(['id', 'action_type', 'module_name', 'description', 'ip_address', 'created_at']);

if ($recentApiLogs->isEmpty()) {
    echo "No API/System logs found.\n";
} else {
    foreach ($recentApiLogs as $log) {
        echo sprintf(
            "[%s] ID:%d | %s | %s | IP:%s\n  %s\n\n",
            $log->created_at->format('Y-m-d H:i:s'),
            $log->id,
            $log->action_type,
            $log->module_name,
            $log->ip_address ?? 'N/A',
            $log->description
        );
    }
}

echo "=== External Medical API Logs ===\n";
$medicalLogs = AuditLog::where('module_name', 'External Medical API')
    ->orWhere('module_name', 'External API')
    ->latest()
    ->take(5)
    ->get(['id', 'action_type', 'module_name', 'description', 'created_at']);

if ($medicalLogs->isEmpty()) {
    echo "No Medical API logs found. Try making an API call in Postman.\n";
} else {
    foreach ($medicalLogs as $log) {
        echo sprintf(
            "[%s] %s | %s\n  %s\n\n",
            $log->created_at->format('Y-m-d H:i:s'),
            $log->action_type,
            $log->module_name,
            $log->description
        );
    }
}
