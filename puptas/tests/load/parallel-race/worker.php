<?php

/**
 * Concurrency Test Harness - Worker Process
 *
 * Spawns as an independent OS-level PHP process, authenticates as the designated
 * role actor, synchronizes to a shared target millisecond timestamp, and calls
 * the specified controller action directly against local MySQL.
 */

$options = getopt('', [
    'base-path:',
    'action:',
    'actor-id:',
    'applicant-user-id:',
    'program-id:',
    'target-time-ms:',
    'worker-id:',
    'out-file:',
]);

$basePath = realpath($options['base-path'] ?? __DIR__ . '/../../..');
$action = $options['action'] ?? 'interviewer-accept';
$actorId = (int)($options['actor-id'] ?? 0);
$applicantUserId = (int)($options['applicant-user-id'] ?? 0);
$programId = (int)($options['program-id'] ?? 12);
$targetTimeMs = (float)($options['target-time-ms'] ?? 0);
$workerId = (int)($options['worker-id'] ?? 1);
$outFile = $options['out-file'] ?? null;

if (!$basePath || !file_exists($basePath . '/vendor/autoload.php')) {
    fwrite(STDERR, "Worker {$workerId}: Invalid base path: {$basePath}\n");
    exit(1);
}

require $basePath . '/vendor/autoload.php';

// 1. Explicitly require target controller from base path before composer autoloads it via junction
if (in_array($action, ['interviewer-accept', 'interviewer-accept-last-slot'], true)) {
    require_once $basePath . '/app/Http/Controllers/InterviewerDashboardController.php';
} elseif ($action === 'registrar-tag') {
    require_once $basePath . '/app/Http/Controllers/RecordStaffDashboardController.php';
} elseif ($action === 'evaluator-pass') {
    require_once $basePath . '/app/Http/Controllers/EvaluatorDashboardController.php';
}

$app = require_once $basePath . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use Illuminate\Support\Facades\Auth;

// 2. HARD GUARDS
$dbHost = config('database.connections.mysql.host', env('DB_HOST'));
$appEnv = config('app.env', env('APP_ENV'));
$allowedHosts = ['127.0.0.1', 'localhost', '::1'];

if (!in_array($dbHost, $allowedHosts, true) || $appEnv !== 'local') {
    fwrite(STDERR, "Worker {$workerId}: FATAL: Only allowed on local MySQL. DB_HOST={$dbHost}, APP_ENV={$appEnv}\n");
    exit(1);
}

// 3. Authenticate actor
$actor = User::find($actorId);
if (!$actor) {
    fwrite(STDERR, "Worker {$workerId}: Actor ID {$actorId} not found.\n");
    exit(1);
}
Auth::login($actor);

// 4. Prepare request
if (in_array($action, ['interviewer-accept', 'interviewer-accept-last-slot'], true)) {
    $request = \Illuminate\Http\Request::create(
        '/interviewer-dashboard/accept/' . $applicantUserId,
        'POST',
        [
            'program_id' => $programId,
            'notes'      => 'Parallel race test run',
        ]
    );
} elseif ($action === 'registrar-tag') {
    $request = \App\Http\Requests\EnrollmentTagRequest::create(
        '/record-staff/tag/' . $applicantUserId,
        'POST',
        ['reason' => 'Verified by parallel race test harness']
    );
    $request->setContainer($app)->setRedirector($app->make(\Illuminate\Routing\Redirector::class));
    $request->validateResolved();
} elseif ($action === 'evaluator-pass') {
    $request = \Illuminate\Http\Request::create(
        '/evaluator/pass/' . $applicantUserId,
        'POST',
        ['note' => 'Passed by parallel race test harness', 'stage' => 'document_evaluator']
    );
} else {
    fwrite(STDERR, "Worker {$workerId}: Unknown action {$action}\n");
    exit(1);
}

// Bind request into application container
$app->instance('request', $request);

// 5. Synchronize with high-precision spin loop to target timestamp
$targetSec = $targetTimeMs / 1000.0;
while (microtime(true) < $targetSec) {
    if (($targetSec - microtime(true)) > 0.05) {
        usleep(5000); // Sleep in 5ms increments while far from target
    }
}

// 6. Execute controller action
$start = microtime(true);
$statusCode = 200;
$responseBody = '';

try {
    if (in_array($action, ['interviewer-accept', 'interviewer-accept-last-slot'], true)) {
        $controller = $app->make(\App\Http\Controllers\InterviewerDashboardController::class);
        $response = $controller->accept($request, $applicantUserId);
    } elseif ($action === 'registrar-tag') {
        $controller = $app->make(\App\Http\Controllers\RecordStaffDashboardController::class);
        $response = $controller->tag($request, $applicantUserId);
    } elseif ($action === 'evaluator-pass') {
        $controller = $app->make(\App\Http\Controllers\EvaluatorDashboardController::class);
        $response = $controller->passApplication($request, $applicantUserId);
    }

    $durationMs = round((microtime(true) - $start) * 1000, 2);
    if (isset($response) && method_exists($response, 'getStatusCode')) {
        $statusCode = $response->getStatusCode();
        $responseBody = $response->getContent();
    } else {
        $statusCode = 200;
        $responseBody = json_encode($response);
    }
} catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
    $durationMs = round((microtime(true) - $start) * 1000, 2);
    $statusCode = $e->getStatusCode();
    $responseBody = json_encode(['message' => $e->getMessage()]);
} catch (\Throwable $e) {
    $durationMs = round((microtime(true) - $start) * 1000, 2);
    $statusCode = 500;
    $responseBody = json_encode([
        'message' => $e->getMessage(),
        'file'    => $e->getFile(),
        'line'    => $e->getLine(),
    ]);
}

$result = [
    'worker_id'         => $workerId,
    'action'            => $action,
    'applicant_user_id' => $applicantUserId,
    'status_code'       => $statusCode,
    'duration_ms'       => $durationMs,
    'response_body'     => json_decode($responseBody, true) ?? $responseBody,
];

if ($outFile) {
    file_put_contents($outFile, json_encode($result, JSON_PRETTY_PRINT));
} else {
    echo json_encode($result, JSON_PRETTY_PRINT) . "\n";
}
