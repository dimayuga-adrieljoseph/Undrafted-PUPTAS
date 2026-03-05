<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->boot();

use Illuminate\Support\Facades\DB;

$logs = DB::table('audit_logs')->orderByDesc('id')->limit(20)->get();
echo "=== Last 20 audit log rows ===\n";
foreach ($logs as $l) {
    echo sprintf(
        "id=%-4s user_id=%-4s action=%-10s module=%-20s desc=%s\n",
        $l->id,
        $l->user_id ?? 'NULL',
        $l->action_type ?? 'NULL',
        $l->module_name ?? 'NULL',
        substr($l->description ?? '', 0, 60)
    );
}
echo "\nTotal rows: " . DB::table('audit_logs')->count() . "\n";
