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
1. **`interviewer-accept`**: Parallel acceptance by department interviewer (`InterviewerDashboardController::accept`). Tests program quota slot decrements and `medical` stage process creation.
2. **`registrar-tag`**: Parallel official enrollment tagging by registrar (`RecordStaffDashboardController::tag`). Tests `enrollment_status` transitions and `audit_logs` record deduplication.
3. **`evaluator-pass`**: Parallel applicant approval by document evaluator (`EvaluatorDashboardController::passApplication`). Tests next-stage (`grade_evaluator`) process row deduplication.

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
.\run_parallel_race.ps1 -Action registrar-tag -Cleanup
.\run_parallel_race.ps1 -Action evaluator-pass -Cleanup
```

---

## Injected Concurrency Stress Window Note
In local testing benchmarks, a temporary `usleep(300000)` (300ms) delay was injected between the pre-transaction status check and `DB::transaction` in both the old baseline code and the fixed code to isolate and widen the check-then-act window under high load. This temporary injection was strictly for testing and never committed to production code. The harness invokes controller actions directly rather than over HTTP, and real-world race frequencies depend on network and database latency.
