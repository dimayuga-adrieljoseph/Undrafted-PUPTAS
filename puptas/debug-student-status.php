<?php
// Check student status in production database
// Run with: php debug-student-status.php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== Student Status Check ===\n\n";

// Check ALL students that should appear in records dashboard
$profiles = \App\Models\ApplicantProfile::with([
    'currentApplication.processes',
    'currentApplication.program',
])
->whereHas('currentApplication', function ($q) {
    $q->where(function ($inner) {
        $inner->whereHas('processes', function ($p) {
            $p->where('stage', 'medical')->where('status', 'completed');
        })
        ->orWhere('enrollment_status', 'officially_enrolled');
    });
})
->get();

echo "Students eligible for records dashboard: " . $profiles->count() . "\n\n";

foreach ($profiles as $profile) {
    $app = $profile->currentApplication;
    $processes = $app?->processes ?? collect();
    $medical = $processes->where('stage', 'medical')->first();

    echo "Student: {$profile->firstname} {$profile->lastname}\n";
    echo "  Student Number: " . ($profile->student_number ?? 'NULL') . "\n";
    echo "  App Status: " . ($app?->status ?? 'NULL') . "\n";
    echo "  Enrollment Status: " . ($app?->enrollment_status ?? 'NULL') . "\n";
    echo "  Medical Stage: " . ($medical?->status ?? 'NULL') . " / " . ($medical?->action ?? 'NULL') . "\n";
    echo "  Program: " . ($app?->program?->name ?? 'NULL') . "\n";
    echo "\n";
}

if ($profiles->isEmpty()) {
    echo "⚠ No students found!\n\n";
    echo "Checking why - looking at all medical processes:\n";
    
    $medicalProcesses = \App\Models\ApplicationProcess::where('stage', 'medical')
        ->where('status', 'completed')
        ->with('application')
        ->get();
    
    echo "Completed medical processes: " . $medicalProcesses->count() . "\n";
    foreach ($medicalProcesses as $mp) {
        echo "  App ID: {$mp->application_id} | Action: {$mp->action} | App Status: {$mp->application?->status} | Enrollment: {$mp->application?->enrollment_status}\n";
    }
}
