<#
.SYNOPSIS
    Orchestrates real OS-level multi-process concurrency testing against local MySQL.

.DESCRIPTION
    Spawns two independent PHP CLI worker processes per round, synchronized via a shared
    UTC millisecond timestamp, to stress-test transactional locking and guard checks across:
      1. interviewer-accept (InterviewerDashboardController::accept)
      2. registrar-tag (RecordStaffDashboardController::tag)
      3. evaluator-pass (EvaluatorDashboardController::passApplication)

.PARAMETER Action
    The action to test: 'interviewer-accept', 'registrar-tag', or 'evaluator-pass'.

.PARAMETER BasePath
    Path to the Laravel application directory (defaults to repo puptas folder).

.PARAMETER N
    Number of applicant rounds to test (default: 20).

.PARAMETER Prefix
    Prefix for test accounts and profiles (default derived from action).

.PARAMETER Cleanup
    Switch to cleanly delete test data created by this harness without running tests.
#>

[CmdletBinding()]
param(
    [ValidateSet('interviewer-accept', 'registrar-tag', 'evaluator-pass')]
    [string]$Action = 'interviewer-accept',

    [string]$BasePath = "",

    [int]$N = 20,

    [string]$Prefix = "",

    [switch]$Cleanup
)

$ErrorActionPreference = 'Stop'

if ([string]::IsNullOrWhiteSpace($BasePath)) {
    $BasePath = (Resolve-Path (Join-Path $PSScriptRoot "../../..")).Path
}

if ([string]::IsNullOrWhiteSpace($Prefix)) {
    $Prefix = switch ($Action) {
        'interviewer-accept' { 'prace_int' }
        'registrar-tag'      { 'prace_tag' }
        'evaluator-pass'     { 'prace_eval' }
    }
}

$seedScript = Join-Path $PSScriptRoot "seed.php"
$workerScript = Join-Path $PSScriptRoot "worker.php"
$scratchDir = Join-Path $PSScriptRoot "results"
if (!(Test-Path $scratchDir)) {
    New-Item -ItemType Directory -Path $scratchDir -Force | Out-Null
}

Write-Host "=================================================================" -ForegroundColor Cyan
Write-Host " PUPTAS OS-Level Parallel Concurrency Test Harness" -ForegroundColor Cyan
Write-Host "=================================================================" -ForegroundColor Cyan
Write-Host "Action:       $Action" -ForegroundColor Yellow
Write-Host "Base Path:    $BasePath" -ForegroundColor Yellow
Write-Host "Prefix:       $Prefix" -ForegroundColor Yellow
Write-Host "Rounds (N):   $N" -ForegroundColor Yellow

# 1. HARD GUARD CHECK
$envFile = Join-Path $BasePath ".env"
if (!(Test-Path $envFile)) {
    Write-Error "FATAL: .env file not found at $BasePath"
    exit 1
}

$envContent = Get-Content $envFile -Raw
$dbHostMatch = [regex]::Match($envContent, '(?m)^DB_HOST=(.+)$')
$appEnvMatch = [regex]::Match($envContent, '(?m)^APP_ENV=(.+)$')

$dbHost = if ($dbHostMatch.Success) { $dbHostMatch.Groups[1].Value.Trim().Trim('"').Trim("'") } else { "unknown" }
$appEnv = if ($appEnvMatch.Success) { $appEnvMatch.Groups[1].Value.Trim().Trim('"').Trim("'") } else { "unknown" }

if ($dbHost -notin @('127.0.0.1', 'localhost', '::1') -or $appEnv -ne 'local') {
    Write-Error "FATAL: Harness can only run locally against MySQL. DB_HOST must be 127.0.0.1 or localhost (got: $dbHost), APP_ENV must be local (got: $appEnv)."
    exit 1
}

# 2. CLEANUP MODE
if ($Cleanup) {
    Write-Host "`n[CLEANUP] Removing test records matching prefix '$Prefix'..." -ForegroundColor Magenta
    $cleanupOutput = & php $seedScript --action $Action --prefix $Prefix --cleanup --base-path $BasePath
    Write-Host $cleanupOutput -ForegroundColor Green
    exit 0
}

# 3. SEED TEST DATA
Write-Host "`n[1/3] Seeding fresh test data ($N applicants)..." -ForegroundColor Yellow
$seedOutput = & php $seedScript --action $Action --prefix $Prefix --n $N --base-path $BasePath
try {
    $seedData = $seedOutput | ConvertFrom-Json
} catch {
    Write-Error "Failed to parse seed output:`n$seedOutput"
    exit 1
}

$actorId = $seedData.actor_id
$applicants = $seedData.applicants
$programId = $seedData.program_id

Write-Host "Seeded $($applicants.Count) applicants under actor ID $actorId (Program: $programId)." -ForegroundColor Green

# 4. EXECUTE CONCURRENT ROUNDS
Write-Host "`n[2/3] Executing $N synchronized parallel rounds..." -ForegroundColor Yellow

$roundResults = @()

for ($i = 0; $i -lt $applicants.Count; $i++) {
    $applicant = $applicants[$i]
    $applicantUserId = $applicant.user_id
    $roundNum = $i + 1

    $outW1 = Join-Path $scratchDir "r_${roundNum}_w1.json"
    $outW2 = Join-Path $scratchDir "r_${roundNum}_w2.json"
    if (Test-Path $outW1) { Remove-Item $outW1 -Force }
    if (Test-Path $outW2) { Remove-Item $outW2 -Force }

    $targetEpochMs = [DateTimeOffset]::UtcNow.ToUnixTimeMilliseconds() + 3000

    $w1Args = @(
        $workerScript,
        "--base-path", $BasePath,
        "--action", $Action,
        "--actor-id", $actorId,
        "--applicant-user-id", $applicantUserId,
        "--target-time-ms", $targetEpochMs,
        "--worker-id", 1,
        "--out-file", $outW1
    )

    $w2Args = @(
        $workerScript,
        "--base-path", $BasePath,
        "--action", $Action,
        "--actor-id", $actorId,
        "--applicant-user-id", $applicantUserId,
        "--target-time-ms", $targetEpochMs,
        "--worker-id", 2,
        "--out-file", $outW2
    )

    $p1 = Start-Process -FilePath "php" -ArgumentList $w1Args -PassThru -NoNewWindow
    $p2 = Start-Process -FilePath "php" -ArgumentList $w2Args -PassThru -NoNewWindow

    Wait-Process -Id @($p1.Id, $p2.Id)

    $r1 = if (Test-Path $outW1) { Get-Content $outW1 -Raw | ConvertFrom-Json } else { @{ status_code = 500; response_body = "Timeout/Crash" } }
    $r2 = if (Test-Path $outW2) { Get-Content $outW2 -Raw | ConvertFrom-Json } else { @{ status_code = 500; response_body = "Timeout/Crash" } }

    $s1 = $r1.status_code
    $s2 = $r2.status_code

    $isDouble200 = ($s1 -eq 200 -and $s2 -eq 200)
    $isCleanPass = (($s1 -eq 200 -and $s2 -eq 409) -or ($s1 -eq 409 -and $s2 -eq 200))

    $statusLabel = if ($isDouble200) { "FAIL (2x 200)" } elseif ($isCleanPass) { "PASS (200/409)" } else { "ANOMALY ($s1/$s2)" }
    $color = if ($isCleanPass) { "Green" } elseif ($isDouble200) { "Red" } else { "Yellow" }

    Write-Host ("  Round {0,2} [Applicant {1}]: W1={2} ({3}ms) | W2={4} ({5}ms) --> {6}" -f `
        $roundNum, $applicantUserId, $s1, $r1.duration_ms, $s2, $r2.duration_ms, $statusLabel) -ForegroundColor $color

    $roundResults += [PSCustomObject]@{
        Round           = $roundNum
        ApplicantUserId = $applicantUserId
        W1Status        = $s1
        W1Duration      = $r1.duration_ms
        W2Status        = $s2
        W2Duration      = $r2.duration_ms
        IsDouble200     = $isDouble200
        IsCleanPass     = $isCleanPass
    }
}

# 5. POST-RUN DATABASE VERIFICATION
Write-Host "`n[3/3] Running database integrity verification..." -ForegroundColor Yellow

$double200Count = ($roundResults | Where-Object { $_.IsDouble200 }).Count
$cleanPassCount = ($roundResults | Where-Object { $_.IsCleanPass }).Count
$server500Count = ($roundResults | Where-Object { $_.W1Status -eq 500 -or $_.W2Status -eq 500 }).Count

$verifyFile = Join-Path $scratchDir "verify.php"
$normalizedBasePath = $BasePath.Replace('\', '/')
$jsonUserIds = ($applicants.user_id | ConvertTo-Json -Compress)

$verifyPhpCode = @"
<?php
require '$normalizedBasePath/vendor/autoload.php';
`$app = require_once '$normalizedBasePath/bootstrap/app.php';
`$kernel = `$app->make(Illuminate\Contracts\Console\Kernel::class);
`$kernel->bootstrap();

use App\Models\Program;
use App\Models\ApplicationProcess;
use App\Models\Application;
use App\Models\AuditLog;
use Illuminate\Support\Facades\DB;

`$action = '$Action';
`$prefix = '$Prefix';
`$userIds = json_decode('$jsonUserIds', true);
`$appIds = Application::whereIn('user_id', `$userIds)->pluck('id')->toArray();

`$res = [];
if (`$action === 'interviewer-accept') {
    `$prog = Program::find($programId);
    `$res['final_slots'] = `$prog ? `$prog->slots : null;
    `$res['duplicates'] = DB::table('application_processes')
        ->select('application_id', DB::raw('COUNT(*) as cnt'))
        ->where('stage', 'medical')
        ->whereIn('application_id', `$appIds)
        ->groupBy('application_id')
        ->having('cnt', '>', 1)
        ->get();
} elseif (`$action === 'registrar-tag') {
    `$res['duplicate_audits'] = DB::table('audit_logs')
        ->select('description', DB::raw('COUNT(*) as cnt'))
        ->where('action_type', 'UPDATE')
        ->where(function(`$q) use (`$userIds) {
            foreach (`$userIds as `$uid) {
                `$q->orWhere('description', 'like', '%user_id: ' . `$uid . ',%');
            }
        })
        ->groupBy('description')
        ->having('cnt', '>', 1)
        ->get();
    `$res['officially_enrolled_count'] = Application::whereIn('user_id', `$userIds)
        ->where('enrollment_status', 'officially_enrolled')
        ->count();
} elseif (`$action === 'evaluator-pass') {
    `$res['duplicates'] = DB::table('application_processes')
        ->select('application_id', DB::raw('COUNT(*) as cnt'))
        ->where('stage', 'grade_evaluator')
        ->whereIn('application_id', `$appIds)
        ->groupBy('application_id')
        ->having('cnt', '>', 1)
        ->get();
}

echo json_encode(`$res);
"@

Set-Content -Path $verifyFile -Value $verifyPhpCode
$verifyRaw = & php $verifyFile
$dbRes = $verifyRaw | ConvertFrom-Json

Write-Host "`n=================================================================" -ForegroundColor Cyan
Write-Host " CONCURRENCY TEST SUMMARY: $Action ($N Rounds)" -ForegroundColor Cyan
Write-Host "=================================================================" -ForegroundColor Cyan
Write-Host "Rounds with Two 200s (Race Failures): $double200Count / $N" -ForegroundColor $(if ($double200Count -gt 0) { "Red" } else { "Green" })
Write-Host "Clean Passes (1x 200 & 1x 409):       $cleanPassCount / $N" -ForegroundColor $(if ($cleanPassCount -eq $N) { "Green" } else { "Yellow" })
Write-Host "Server 500 Errors:                    $server500Count" -ForegroundColor $(if ($server500Count -gt 0) { "Red" } else { "Green" })

if ($Action -eq 'interviewer-accept') {
    $expectedSlots = 50 - $N
    Write-Host "Final Program Slots:                  $($dbRes.final_slots) (Starting: 50, Expected: $expectedSlots)"
    Write-Host "Duplicate Medical Processes:          $($dbRes.duplicates.Count)" -ForegroundColor $(if ($dbRes.duplicates.Count -gt 0) { "Red" } else { "Green" })
} elseif ($Action -eq 'registrar-tag') {
    Write-Host "Officially Enrolled Applications:     $($dbRes.officially_enrolled_count) / $N"
    Write-Host "Duplicate Audit Log Sets:             $($dbRes.duplicate_audits.Count)" -ForegroundColor $(if ($dbRes.duplicate_audits.Count -gt 0) { "Red" } else { "Green" })
} elseif ($Action -eq 'evaluator-pass') {
    Write-Host "Duplicate Next-Stage Processes:       $($dbRes.duplicates.Count)" -ForegroundColor $(if ($dbRes.duplicates.Count -gt 0) { "Red" } else { "Green" })
}
Write-Host "=================================================================`n" -ForegroundColor Cyan
