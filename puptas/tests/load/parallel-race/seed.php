<?php

/**
 * Concurrency Test Harness - Database Seeder & Cleaner
 *
 * Strictly restricted to local development environments (DB_HOST=127.0.0.1/localhost, APP_ENV=local).
 * Never disables foreign key constraints. Cleans up only records created by this harness.
 */

$options = getopt('', ['action:', 'prefix:', 'n:', 'cleanup', 'base-path:']);

$basePath = isset($options['base-path']) ? realpath($options['base-path']) : realpath(__DIR__ . '/../../..');
if (!$basePath || !file_exists($basePath . '/vendor/autoload.php')) {
    fwrite(STDERR, "FATAL: Could not locate Laravel application at base path: " . ($options['base-path'] ?? __DIR__ . '/../../..') . "\n");
    exit(1);
}

require $basePath . '/vendor/autoload.php';
$app = require_once $basePath . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Models\ApplicantProfile;
use App\Models\Application;
use App\Models\Grade;
use App\Models\UserFile;
use App\Models\ApplicationProcess;
use App\Models\AuditLog;
use App\Models\Program;
use App\Enums\RoleId;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// 1. HARD GUARDS
$dbHost = config('database.connections.mysql.host', env('DB_HOST'));
$appEnv = config('app.env', env('APP_ENV'));

$allowedHosts = ['127.0.0.1', 'localhost', '::1'];
if (!in_array($dbHost, $allowedHosts, true) || $appEnv !== 'local') {
    fwrite(STDERR, "FATAL: Race test harness is strictly restricted to local development environments.\n");
    fwrite(STDERR, "Current DB_HOST: {$dbHost} (Allowed: 127.0.0.1, localhost)\n");
    fwrite(STDERR, "Current APP_ENV: {$appEnv} (Allowed: local)\n");
    exit(1);
}

$action = $options['action'] ?? 'interviewer-accept';
$prefix = $options['prefix'] ?? ('prace_' . str_replace('-', '_', $action));
$n = isset($options['n']) ? (int)$options['n'] : 20;
$isCleanup = isset($options['cleanup']);
$programId = 12; // Program 12 = BSCS

// 2. EXPLICIT CLEANUP HANDLER
if ($isCleanup) {
    // Collect all harness-created test users matching prefix (including soft deleted)
    $testUserIds = User::withTrashed()->where('email', 'like', "{$prefix}_%")->pluck('id')->toArray();
    $actorUserIds = User::withTrashed()->where('email', 'like', "actor_{$prefix}%")->pluck('id')->toArray();
    $allTargetUserIds = array_unique(array_merge($testUserIds, $actorUserIds));

    if (empty($allTargetUserIds)) {
        echo json_encode(['status' => 'cleaned', 'deleted_users' => 0, 'message' => 'No matching test users found for prefix ' . $prefix]) . "\n";
        exit(0);
    }

    $allAppIds = Application::whereIn('user_id', $allTargetUserIds)->pluck('id')->toArray();

    // Clean up strictly in child-to-parent order (FK integrity preserved without SET FOREIGN_KEY_CHECKS=0)
    // 1. Audit logs
    AuditLog::where(function ($query) use ($allTargetUserIds, $prefix) {
        $query->whereIn('user_id', $allTargetUserIds)
              ->orWhere('description', 'like', "%{$prefix}%");
    })->delete();

    // 2. User files (force delete soft deletes)
    UserFile::whereIn('user_id', array_map('strval', $allTargetUserIds))->forceDelete();

    // 3. Application processes
    if (!empty($allAppIds)) {
        ApplicationProcess::whereIn('application_id', $allAppIds)->delete();
    }
    ApplicationProcess::whereIn('performed_by', $allTargetUserIds)->delete();

    // 4. Grades
    Grade::whereIn('user_id', array_map('strval', $allTargetUserIds))->delete();

    // 5. Applications
    if (!empty($allAppIds)) {
        Application::whereIn('id', $allAppIds)->delete();
    }

    // 6. Applicant profiles
    ApplicantProfile::whereIn('user_id', $allTargetUserIds)->delete();

    // 7. Program assignments
    DB::table('program_user')->whereIn('user_id', $allTargetUserIds)->delete();

    // 8. Users (force delete soft deletes)
    $deletedUsersCount = User::withTrashed()->whereIn('id', $allTargetUserIds)->forceDelete();

    echo json_encode([
        'status' => 'cleaned',
        'prefix' => $prefix,
        'deleted_users' => $deletedUsersCount,
        'deleted_applications' => count($allAppIds),
    ]) . "\n";
    exit(0);
}

// 3. SEEDING HANDLER
// Ensure Program 12 exists and set initial slots
$program = Program::find($programId);
if (!$program) {
    fwrite(STDERR, "FATAL: Program ID {$programId} does not exist in local database.\n");
    exit(1);
}
if ($action === 'interviewer-accept') {
    $program->slots = 50;
    $program->save();
} elseif ($action === 'interviewer-accept-last-slot') {
    $program->slots = 1;
    $program->save();
}

// Create or retrieve dedicated test actor (never store passwords in files)
$actorEmail = "actor_{$prefix}@example.local";
$actor = User::withTrashed()->where('email', $actorEmail)->first();

$roleMap = [
    'interviewer-accept'           => RoleId::Interviewer->value,
    'interviewer-accept-last-slot' => RoleId::Interviewer->value,
    'registrar-tag'                => RoleId::Registrar->value,
    'evaluator-pass'               => RoleId::DocumentEvaluator->value,
];
$actorRole = $roleMap[$action] ?? RoleId::Interviewer->value;

if (!$actor) {
    $actor = new User();
    $actor->forceFill([
        'email'         => $actorEmail,
        'firstname'     => 'Harness',
        'lastname'      => 'Actor',
        'role_id'       => $actorRole,
        'password'      => Hash::make(Str::random(32)),
        'sex'           => 'Male',
    ])->save();
} else {
    if ($actor->trashed()) {
        $actor->restore();
    }
    $actor->forceFill(['role_id' => $actorRole])->save();
}

// Ensure actor program assignment for interviewer or evaluator
if (in_array($action, ['interviewer-accept', 'interviewer-accept-last-slot', 'evaluator-pass'], true)) {
    DB::table('program_user')->updateOrInsert(
        ['user_id' => $actor->id, 'program_id' => $programId],
        ['role_id' => $actor->role_id]
    );
}

// Seed N test applicants with unique run timestamp (2*N for interviewer-accept-last-slot)
$runTimestamp = time();
$seededApplicants = [];
$applicantTotal = ($action === 'interviewer-accept-last-slot') ? ($n * 2) : $n;

for ($i = 1; $i <= $applicantTotal; $i++) {
    $applicantEmail = "{$prefix}_{$runTimestamp}_{$i}@example.local";

    $user = new User();
    $user->forceFill([
        'email'         => $applicantEmail,
        'firstname'     => 'Race',
        'lastname'      => 'Applicant' . $i,
        'role_id'       => RoleId::Applicant->value,
        'password'      => Hash::make(Str::random(32)),
        'sex'           => 'Female',
    ])->save();

    ApplicantProfile::create([
        'user_id'       => $user->id,
        'email'         => $applicantEmail,
        'firstname'     => 'Race',
        'lastname'      => 'Applicant' . $i,
        'strand'        => 'STEM',
        'sex'           => 'Female',
        'contactnumber' => '09123456789',
    ]);

    $appStatus = ($action === 'registrar-tag') ? 'accepted' : 'submitted';
    $enrollmentStatus = ($action === 'registrar-tag') ? 'temporary' : 'temporary';

    $application = Application::create([
        'user_id'           => $user->id,
        'program_id'        => $programId,
        'status'            => $appStatus,
        'enrollment_status' => $enrollmentStatus,
        'submitted_at'      => now(),
    ]);

    // Action-specific prerequisites
    if (in_array($action, ['interviewer-accept', 'interviewer-accept-last-slot'], true)) {
        Grade::create([
            'user_id'     => (string) $user->id,
            'mathematics' => 95,
            'science'     => 95,
            'english'     => 95,
        ]);

        ApplicationProcess::create([
            'application_id' => $application->id,
            'stage'          => 'grade_evaluator',
            'status'         => 'completed',
            'action'         => 'passed',
            'performed_by'   => null,
        ]);

        ApplicationProcess::create([
            'application_id' => $application->id,
            'stage'          => 'interviewer',
            'status'         => 'in_progress',
            'performed_by'   => null,
        ]);
    } elseif ($action === 'registrar-tag') {
        // Separation of duties: performed_by must not be actor ID
        ApplicationProcess::create([
            'application_id' => $application->id,
            'stage'          => 'medical',
            'status'         => 'completed',
            'performed_by'   => 999999, // Dummy non-actor evaluator
        ]);

        // Require COR front and back uploaded & approved
        UserFile::create([
            'user_id'        => (string) $user->id,
            'application_id' => $application->id,
            'type'           => 'cor_front',
            'file_path'      => 'harness_cor_front.pdf',
            'original_name'  => 'cor_front.pdf',
            'status'         => UserFile::STATUS_APPROVED,
        ]);

        UserFile::create([
            'user_id'        => (string) $user->id,
            'application_id' => $application->id,
            'type'           => 'cor_back',
            'file_path'      => 'harness_cor_back.pdf',
            'original_name'  => 'cor_back.pdf',
            'status'         => UserFile::STATUS_APPROVED,
        ]);
    } elseif ($action === 'evaluator-pass') {
        ApplicationProcess::create([
            'application_id' => $application->id,
            'stage'          => 'document_evaluator',
            'status'         => 'in_progress',
            'performed_by'   => null,
        ]);

        UserFile::create([
            'user_id'        => (string) $user->id,
            'application_id' => $application->id,
            'type'           => 'psa_birth_certificate',
            'file_path'      => 'harness_psa.pdf',
            'original_name'  => 'psa.pdf',
            'status'         => UserFile::STATUS_PENDING,
        ]);
    }

    $seededApplicants[] = [
        'user_id'        => $user->id,
        'application_id' => $application->id,
        'email'          => $applicantEmail,
    ];
}

echo json_encode([
    'status'           => 'seeded',
    'action'           => $action,
    'prefix'           => $prefix,
    'run_timestamp'    => $runTimestamp,
    'actor_id'         => $actor->id,
    'program_id'       => $programId,
    'applicant_count'  => count($seededApplicants),
    'applicants'       => $seededApplicants,
]) . "\n";
