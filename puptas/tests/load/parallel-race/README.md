# Multi-Process OS-Level Concurrency Test Harness

## Overview
This test harness verifies pessimistic database row locking (`lockForUpdate()`) and stateful transaction guards under true OS-level parallelism on MySQL.

### Why OS-Level Multi-Process Testing?
On Windows development environments:
- `php artisan serve` is strictly single-process and single-threaded.
- `PHP_CLI_SERVER_WORKERS` is not supported on Windows.
- HTTP-level race tests against `php artisan serve` queue TCP connections sequentially at the socket layer, masking application-level race conditions.

To reproduce and verify race conditions locally on Windows, this harness spawns two independent OS-level PHP processes (`Start-Process php`) synchronized to a shared UTC millisecond timestamp, invoking controller actions directly against local MySQL.

---

## Supported Actions
1. **`interviewer-accept`**: Two PHP processes calling `InterviewerDashboardController::accept` in parallel for the same applicant. Tests program quota slot decrements and `medical` stage process creation.
2. **`interviewer-accept-last-slot`**: Two PHP processes calling `InterviewerDashboardController::accept` in parallel for two different applicants competing for the last remaining slot (`slots = 1`) in the same program. Verifies `Program::lockForUpdate()` serialization, `slots = 0` floor (no negative slots), 1x status 200 and 1x status 400 (`No available slots in the selected program.`), and single `medical` process creation across the two applicants.
3. **`registrar-tag`**: Two PHP processes calling `RecordStaffDashboardController::tag` in parallel. Tests `enrollment_status` transitions and `audit_logs` record deduplication.
4. **`evaluator-pass`**: Two PHP processes calling `EvaluatorDashboardController::passApplication` in parallel. Tests next-stage (`grade_evaluator`) process row deduplication.

---

## Safety Guarantees & Hard Guards
- **Environment Gating**: The harness immediately aborts execution unless `DB_HOST` is `127.0.0.1` or `localhost` and `APP_ENV` is `local`.
- **Database Integrity**: Never runs `SET FOREIGN_KEY_CHECKS=0`. All records are inserted and deleted following strict foreign-key dependency hierarchy.
- **Prefix Isolation**: All test users, profiles, and files use action-specific prefixes (e.g. `prace_int_`, `prace_tag_`, `prace_eval_`).
- **Zero Static Credentials**: Actor and applicant passwords use dynamically generated `Hash::make(Str::random(32))`. No credentials or tokens are stored in repository files.
- **Dedicated Cleanup**: An explicit `-Cleanup` flag safely removes only harness-created test rows in reverse foreign key order.

---

## File Structure
- `run_parallel_race.ps1`: PowerShell orchestrator script managing process lifecycles and gathering database metrics.
- `seed.php`: Seed script generating isolated test applicants, prerequisite files, and actor assignments (or cleaning them up with `--cleanup`).
- `worker.php`: Independent CLI worker that boots Laravel, authenticates as the actor, synchronizes to a millisecond timestamp, and calls the controller method directly.

---

## Usage

### 1. Run Concurrency Test Against Current Branch
```powershell
# Interviewer accept (20 applicants, Program 12)
.\run_parallel_race.ps1 -Action interviewer-accept -N 20

# Interviewer accept last slot (20 rounds, 2 different applicants per round, Program 12 with slots = 1)
.\run_parallel_race.ps1 -Action interviewer-accept-last-slot -N 20

# Registrar tag (20 applicants)
.\run_parallel_race.ps1 -Action registrar-tag -N 20

# Evaluator pass (20 applicants)
.\run_parallel_race.ps1 -Action evaluator-pass -N 20
```

### 2. Run Concurrency Test Against Baseline Worktree
Pass the path to the baseline worktree's `puptas` folder via `-BasePath`:
```powershell
.\run_parallel_race.ps1 -Action registrar-tag -N 20 -BasePath "C:\Users\Dion\Desktop\Undrafted-PUPTAS-baseline\puptas"
```

### 3. Clean Up Test Data
To remove test users, profiles, files, applications, and audit entries created by the harness:
```powershell
.\run_parallel_race.ps1 -Action interviewer-accept -Cleanup
.\run_parallel_race.ps1 -Action interviewer-accept-last-slot -Cleanup
.\run_parallel_race.ps1 -Action registrar-tag -Cleanup
.\run_parallel_race.ps1 -Action evaluator-pass -Cleanup
```

---

## Injected Concurrency Stress Window Note
In local testing benchmarks, a temporary `usleep(300000)` (300ms) delay was added as a temporary manual edit between the pre-transaction status check and `DB::transaction` in both the old baseline code and the fixed code to isolate and widen the check-then-act window; it is not part of the harness and was never committed to production code. Each round spawns two PHP processes that invoke the controller method directly in PHP (rather than sending two HTTP requests), and real-world race frequencies depend on network and database latency.
