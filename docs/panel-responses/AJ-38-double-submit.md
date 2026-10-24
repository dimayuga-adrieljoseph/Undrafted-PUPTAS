# Question #38: Double Submit Protection & Concurrency

## 1. Panel Question
> "What happens if the applicant clicks Submit twice because the page is slow? Maganda if the system prevents duplicate applications or duplicate workflow actions."

## 2. Direct Answer
If an applicant clicks Submit twice, the frontend modal mitigates accidental double-clicks by immediately closing the confirmation dialog, enabling a reactive `submitting` boolean flag, and disabling the submit button while showing a spinner. On the server side, when sequential repeat submissions arrive, `ConfirmationService::submitApplication` checks `canSubmitApplication`, which detects that the application status is already `'submitted'` and cleanly rejects the second request with an HTTP 409 Conflict.

Under concurrent execution, the system serializes submissions using a pessimistic database row lock (`User::lockForUpdate()`) inside `DB::transaction`. When concurrent parallel requests are dispatched by the same applicant, the first request acquires the exclusive lock and processes the submission, while subsequent requests block on the lock until commit. Upon lock acquisition, the subsequent requests observe `status = 'submitted'`, trigger the `canSubmitApplication` check, and abort with an HTTP 409 Conflict. 

Live concurrent load testing on Railway Staging with 20 parallel virtual users firing simultaneous batch submissions confirmed that duplicate submissions dropped from **14/20 (70% failure baseline)** to **0/20 (100% clean single submission)**, with **0 duplicate workflow processes** created in the database.

Furthermore, this pessimistic row locking and re-check pattern is systematically extended to all **evaluator, interviewer, and registrar workflow actions** (`accept`, `reject`, `pass`, `return`, `tag`, `untag`, `change-course`). Parallel duplicate requests cannot duplicate processes, double-decrement program quotas, or generate redundant audit logs.

---

## 3. How It Works

### Frontend Mitigation
In `ApplicationReviewModal.vue` (`resources/js/Pages/Modal/ApplicationReviewModal.vue:201-204, 229-231, 750-758`):
1. The applicant clicks "Submit Application", which opens a confirmation dialog (`openSubmitConfirmation`).
2. In the confirmation dialog, clicking "Yes, Submit Application" triggers `confirmAndSubmit()`.
3. `confirmAndSubmit()` immediately unmounts the confirmation dialog (`showSubmitConfirmation.value = false`) and invokes `submitApplication()`.
4. Inside `submitApplication()`, the reactive state `submitting.value = true` disables the button (`:disabled="!canSubmitApplication || submitting"`) and renders a spinning SVG loader.
5. In the `finally` block, `submitting.value = false` resets state once the network promise settles.

### Backend Concurrency Control: Applicant Submission
In `ConfirmationService::submitApplication` (`app/Services/ConfirmationService.php:293-358`):
```php
return DB::transaction(function () use ($user, $validated) {
    // 1. Lock the user's row to serialize concurrent submissions from the same applicant
    User::lockForUpdate()->find($user->id);

    // 2. Validate submission cutoff settings
    $isOverrideAllowed = false;
    $testPasser = $user->testPasser;
    if ($testPasser) {
        if ($this->cutoffSettingsService->isScoreAllowed((float) $testPasser->pupcet_total_score) ||
            $this->cutoffSettingsService->isEmailAllowed($user->email)) {
            $isOverrideAllowed = true;
        }
    }

    if (!$isOverrideAllowed && $this->cutoffSettingsService->isCutoffPassed()) {
        abort(422, 'The application submission period has closed.');
    }

    $profile = $user->applicantProfile;

    // 3. Load or create the draft application
    $application = $this->applicationRepository->firstOrCreate(
        ['user_id' => $user->id],
        [
            'status' => 'draft',
            'program_id' => $profile?->first_choice_program,
            'second_choice_id' => $profile?->second_choice_program,
            'third_choice_id' => $profile?->third_choice_program,
        ]
    );

    // 4. Validate submission state
    $submissionCheck = $this->canSubmitApplication($application);
    if (!$submissionCheck['can_submit']) {
        abort($submissionCheck['status_code'], $submissionCheck['message']);
    }

    // 5. Commit application submission & create document_evaluator process
    $application->update([
        'status' => 'submitted',
        'submitted_at' => now(),
        'program_id' => $validated['program_id'],
        'second_choice_id' => $validated['second_choice_id'] ?? null,
        'third_choice_id' => $validated['third_choice_id'] ?? null,
    ]);

    $application->processes()->create([
        'stage' => 'document_evaluator',
        'status' => 'in_progress',
        'performed_by' => null,
    ]);

    return $application;
});
```

### Execution Flow with Pessimistic Locking
```
Request 1 (T0)                          Request 2 (T0 + 1ms)
     │                                       │
     ▼                                       ▼
DB::transaction begins                  DB::transaction begins
     │                                       │
User::lockForUpdate()->find($id)        User::lockForUpdate()->find($id)
  (Acquires exclusive row lock)              (BLOCKS / WAITS on lock)
     │                                       │
Loads application (status: 'draft')          │ (waiting...)
canSubmitApplication() -> true               │ (waiting...)
application->update(status: 'submitted')     │ (waiting...)
application->processes()->create(...)        │ (waiting...)
DB::transaction commits                      │ (waiting...)
     │                                       ▼
  (Lock released)                       Lock acquired!
     │                                       │
Returns HTTP 200 OK                     Loads application (status: 'submitted')
                                        canSubmitApplication() -> status === 'submitted'
                                             └───► returns status_code = 409
                                        abort(409, 'Already submitted')
                                        DB::transaction rolls back
                                             │
                                        Returns HTTP 409 Conflict
```

---

### Backend Concurrency Control: Workflow Actions

Parallel duplicate requests during admissions processing (e.g., evaluator double-clicking "Pass", interviewer simultaneous accept clicks, or registrar double-tagging) are serialized using the same pattern:
1. **Interviewer Accept (`InterviewerDashboardController::accept`)**:
   - Inside `DB::transaction`, acquires exclusive lock on the application row: `Application::lockForUpdate()->findOrFail($application->id)`.
   - Re-checks `$freshApplication->status` (must be `submitted`, `returned`, or `transferred`).
   - Re-checks and locks the in-progress process row: `ApplicationProcess::lockForUpdate()->where('id', $inProgress->id)->where('status', 'in_progress')->first()`. Aborts with HTTP 409 if stale.
   - Locks the program quota row second: `Program::lockForUpdate()->findOrFail($programId)`. Enforcing a consistent lock hierarchy uses a consistent lock order (Application, then Program) in the paths changed here.
   - Decrements program quota slots (`$program->slots -= 1`).
   - Creates the next stage (`medical`) only if none exists yet (`!$medicalExists`).
2. **Interviewer Reject (`InterviewerDashboardController::reject`)**:
   - Inside `DB::transaction`, locks `Application::lockForUpdate()`, re-checks application status, and re-checks and locks `ApplicationProcess::lockForUpdate()`. Aborts with HTTP 409 if already settled.
3. **Evaluator Pass (`EvaluatorDashboardController::passApplication`)**:
   - Inside `DB::transaction`, re-fetches and locks `ApplicationProcess::lockForUpdate()->whereIn('status', ['in_progress', 'returned'])`. Aborts with HTTP 409 if already completed.
   - Next-stage process (`grade_evaluator` or `interviewer`) is created only if none exists yet (`!$nextExists`).
4. **Evaluator Reject (`EvaluatorDashboardController::rejectApplication`)**:
   - Inside `DB::transaction`, re-fetches and locks `ApplicationProcess::lockForUpdate()`. Aborts with HTTP 409 if already finalized.
5. **Evaluator Return (`ManagesApplicationFiles::returnFiles`, `returnApplication`)**:
   - Inside `DB::transaction`, re-fetches and locks `ApplicationProcess::lockForUpdate()`. Aborts with HTTP 409 if already completed.
6. **Enrollment Tag (`RecordStaffDashboardController::tag`)**:
   - Inside `DB::beginTransaction()`, locks `Application::lockForUpdate()->findOrFail($application->id)`.
   - Re-checks `$freshApplication->enrollment_status === 'officially_enrolled'`. If true, rolls back immediately, returns HTTP 409 (`"Application is already officially enrolled."`), and writes **zero** duplicate audit logs.
7. **Enrollment Untag (`RecordStaffDashboardController::untag`)**:
   - Inside `DB::beginTransaction()`, locks `Application::lockForUpdate()->findOrFail($application->id)`.
   - Re-checks `$freshApplication->enrollment_status !== 'officially_enrolled'`. If not officially enrolled, rolls back, returns HTTP 400 (`"Application is not officially enrolled."`), and writes **zero** duplicate audit logs.
8. **Change Course (`RecordStaffDashboardController::changeCourse`)**:
   - Inside `DB::beginTransaction()`, locks `Application::lockForUpdate()->findOrFail($application->id)`.
   - Re-checks `$freshApplication->program_id == $newProgramId`. If unchanged, rolls back and returns HTTP 422 (`"The selected program must be different from the current program."`).
   - Note on slot updates: `changeCourse` updates application assignment and transfers enrollment records, but does not mutate the `slots` column of programs.

### Why a Unique Index on `application_processes(application_id, stage)` Would Break the System
An alternative database-level constraint—adding a unique index on `(application_id, stage)`—was analyzed and rejected:
1. **Return and Resubmit Cycles:** In `ConfirmationService::resubmitApplication` (`app/Services/ConfirmationService.php:672-700`), when an applicant addresses deficiencies and resubmits, the system re-activates the review pipeline. If historical processes are archived rather than overwritten, a unique index prevents subsequent review cycles.
2. **Records Stage Re-entry:** The `records` stage records multiple distinct events for an applicant. In `RecordStaffDashboardController::changeCourse` (`app/Http/Controllers/RecordStaffDashboardController.php:455-464`), every program reassignment creates an `ApplicationProcess` row with `stage = 'records'`, `action = 'course_changed'`, and `status = 'completed'`. When the registrar subsequently tags the applicant as officially enrolled (`RecordStaffDashboardController.php:582-593`), an additional `stage = 'records'` row is created. A unique constraint on `(application_id, stage)` would trigger a fatal SQL unique constraint violation on course transfers or retagging.
3. **Audit Trail Integrity:** `application_processes` functions as a chronological event log of stage transitions, not a strict 1-to-1 attribute entity. Therefore, application serialization via pessimistic row locking provides the correct concurrency safeguard without breaking lifecycle event logging.

---

## 4. Evidence

| Claim | File:Lines | Note |
| :--- | :--- | :--- |
| canSubmitApplication 409 logic | `puptas/app/Services/ConfirmationService.php:266-272` | Returns `can_submit: false`, status 409 if status === 'submitted' |
| Transactional submission logic | `puptas/app/Services/ConfirmationService.php:293-358` | Wraps submission in `DB::transaction`, creates process |
| Pessimistic row locking (submit) | `puptas/app/Services/ConfirmationService.php:295` | `User::lockForUpdate()->find($user->id)` serializes submits |
| Controller submission handler | `puptas/app/Http/Controllers/ConfirmationController.php:47-82` | Catches 409 HttpException, maps client message |
| Interviewer accept row locking | `puptas/app/Http/Controllers/InterviewerDashboardController.php:261-278` | Locks Application, re-checks process, locks Program second |
| Interviewer reject row locking | `puptas/app/Http/Controllers/InterviewerDashboardController.php:403-417` | Locks Application, re-checks process, aborts 409 if stale |
| Evaluator pass process lock | `puptas/app/Http/Controllers/EvaluatorDashboardController.php:194-202` | `ApplicationProcess::lockForUpdate()`, creates next stage once |
| Evaluator reject process lock | `puptas/app/Http/Controllers/EvaluatorDashboardController.php:279-286` | `ApplicationProcess::lockForUpdate()`, aborts 409 if stale |
| Evaluator return process lock | `puptas/app/Http/Traits/ManagesApplicationFiles.php:386-394, 488-496` | `ApplicationProcess::lockForUpdate()` in returnFiles & returnApp |
| Official enrollment tag lock | `puptas/app/Http/Controllers/RecordStaffDashboardController.php:604-612` | `Application::lockForUpdate()`, aborts 409, 0 duplicate audit |
| Enrollment untag lock | `puptas/app/Http/Controllers/RecordStaffDashboardController.php:681-689` | `Application::lockForUpdate()`, aborts 400, 0 duplicate audit |
| Course change lock | `puptas/app/Http/Controllers/RecordStaffDashboardController.php:434-439` | `Application::lockForUpdate()`, aborts 422 if same program |
| Frontend confirm & submit handler | `puptas/resources/js/Pages/Modal/ApplicationReviewModal.vue:201-204` | Hides modal immediately, calls `submitApplication()` |
| Frontend submitting flag state | `puptas/resources/js/Pages/Modal/ApplicationReviewModal.vue:229-231, 272` | Sets `submitting.value = true`, resets in `finally` |
| Frontend button disabled attribute | `puptas/resources/js/Pages/Modal/ApplicationReviewModal.vue:750-758` | `:disabled="submitting"` with spinner in confirm dialog |
| Automated sequential repeat test | `puptas/tests/Feature/DoubleSubmitSequentialTest.php:14-88` | Asserts 200 first, 409 second, exactly 1 process row |
| Workflow concurrency feature test | `puptas/tests/Feature/WorkflowConcurrencyTest.php:1-280` | 8 sequential duplicate action tests across all roles |
| Automated concurrent race test (k6) | `puptas/tests/load/double-submit-race.js:1-128` | k6 batch script firing 3 simultaneous submits per VU |
| Parallel interviewer accept race script | `puptas/tests/load/interviewer-accept-race.ps1:1-290` | Multi-round parallel race test with synchronized start times |
| OS-level multi-process race harness | `puptas/tests/load/parallel-race/` | CLI test harness (`run_parallel_race.ps1`, `seed.php`, `worker.php`, `README.md`) |

---

## 5. Test Evidence

### 1. PHPUnit Automated Feature Test (`tests/Feature/DoubleSubmitSequentialTest.php`)
Simulates sequential repeat submissions from the same authenticated applicant:
- The first request executes `POST /user/application/submit` and receives **HTTP 200**.
- The second identical request executes sequentially and receives **HTTP 409 Conflict** with message `"Application has already been submitted."`.
- Database assertion confirms that exactly **one** `document_evaluator` process row exists in `application_processes`.
- **Test Output:** `1 tests, 7 assertions, 0 failures` (in 0.84s via Pest with SQLite).

### 2. Workflow Actions Concurrency Test Suite (`tests/Feature/WorkflowConcurrencyTest.php`)
Simulates repeated sequential requests across all evaluator, interviewer, and registrar workflow actions:
1. `second sequential interviewer accept returns 409 with no extra process row or slot decrement` (HTTP 409, slot count preserved, medical process created exactly once).
2. `second sequential interviewer reject returns 409 with no extra process row` (HTTP 409, no duplicate process row).
3. `second sequential evaluator pass returns 409 with no extra next-stage process row` (HTTP 409, next-stage process count preserved).
4. `second sequential evaluator reject returns 409 with no extra process row` (HTTP 409, no duplicate process row).
5. `second sequential evaluator return after process is completed returns 409 with no extra process row` (HTTP 409, process count preserved).
6. `second sequential tag returns 409 with exactly one audit row` (HTTP 409, enrollment status preserved as `officially_enrolled`, exactly 1 audit row written).
7. `second sequential untag returns 400 with exactly one audit row` (HTTP 400, enrollment status preserved as `temporary`, exactly 1 audit row written).
8. `second sequential change-course to same program returns 422 with no extra process row` (HTTP 422, process row count preserved).
- **Test Output:** `8 tests, 36 assertions, 0 failures` (exit code `0`).
- *Note on SQLite in Automated Testing:* SQLite in-memory ignores `lockForUpdate()`, meaning these automated tests verify that business status guards, process idempotency, and audit trail count invariants hold sequentially without regressions.
- **Combined Security + Concurrency Regression Suite (7 test files):** `164 tests, 453 assertions, 0 failures` (exit code `0`).

### 3. Live Staging Load Test Verification (`tests/load/double-submit-race.js`)
Executed on Railway Staging using 20 concurrent virtual users mapped to seeded test accounts (`testapplicant1@gmail.com` .. `testapplicant20@gmail.com`), firing 3 identical simultaneous POST requests via `http.batch([req, req, req])`:

| Metric | Before Fix (Baseline Staging) | After Fix (`User::lockForUpdate`) | Verification Outcome |
| :--- | :---: | :---: | :---: |
| VUs with multiple 200s (`vus_with_multiple_200s`) | **14 / 20 (70% race failure)** | **0 / 20 (0%)** | **RESOLVED** |
| VUs with clean single 200 (`vus_with_exactly_one_200`) | **6 / 20 (30%)** | **20 / 20 (100%)** | **RESOLVED** |
| Server 500 Error count (`server_500_errors`) | **0** | **0** | **PASS** |
| Overall k6 Check Success Rate | **82.50%** | **100.00%** | **PASS** |
| Duplicate Database Processes in MySQL | **14 duplicate sets** | **0 duplicate sets** | **RESOLVED** |

### 4. Parallel Workflow Actions Race Tests (`tests/load/parallel-race/`)
To verify pessimistic database row locking (`lockForUpdate()`) and stateful idempotency under MySQL concurrency:
- **Committed Harness Path:** `puptas/tests/load/parallel-race/` (contains `run_parallel_race.ps1`, `seed.php`, `worker.php`, and `README.md`).
- **Hard Safety Guards:** Refuses to execute unless `DB_HOST` is `127.0.0.1` or `localhost` and `APP_ENV` is `local`.
- **Database Integrity:** Never runs `SET FOREIGN_KEY_CHECKS=0`. Cleanups delete strictly in reverse foreign-key order using isolated prefixes (`prace_*`). No credentials stored in files.
- **Synchronized Dispatch:** Uses a millisecond epoch synchronization target (`[DateTimeOffset]::UtcNow.ToUnixTimeMilliseconds() + 3000`) so concurrent worker processes invoke methods at the exact same millisecond.

#### 4.1. Single-Process HTTP Negative Control Finding (`php artisan serve` on Windows)
When tested over HTTP against `php artisan serve` (port 8001 baseline vs port 8000 fixed, $N=10$):
- Both branches returned 10/10 rounds with 1x 200 and 1x 409, and zero duplicate rows.
- **Root Cause:** `php artisan serve` is strictly single-process and single-threaded on Windows (`PHP_CLI_SERVER_WORKERS` is unsupported on Windows). Incoming concurrent HTTP connections were queued and serialized at the webserver socket layer, masking any application-layer race window in the old code.

#### 4.2. OS-Level Multi-Process Parallel Concurrency Benchmark (Real Multi-Process, $N=20$ per action)
To achieve genuine OS-level parallelism bypassing the single-threaded web server limitation, the committed harness (`puptas/tests/load/parallel-race/run_parallel_race.ps1`) spawned two separate, independent PHP CLI processes via PowerShell `Start-Process` per applicant round:
1. **Parallel Execution Architecture:** Each process independently booted Laravel, authenticated as the designated actor, synchronized to a shared UTC millisecond target, and called the controller action directly against local MySQL 8.0.
2. **Concurrency Stress Window:** To isolate and widen the check-then-act race window between the pre-transaction status check and `DB::transaction`, a temporary `usleep(300000)` (300ms) delay was injected in both the baseline worktree (`8326deb4^` at `ade6ab1a`) and a throwaway test run of the fixed branch. **Neither temporary delay was committed to production code.**
3. **Execution Mode:** The harness calls the controller action directly rather than over HTTP to bypass web server connection queueing. The real-world frequency of the race was not measured.
4. **Tested Actions ($N=20$ Fresh Local Applicants Per Run):**
   - **Interviewer Accept (`InterviewerDashboardController::accept`)**: Parallel interviewer acceptance on Program 12 (`BSCS`, 50 initial slots).
   - **Registrar Tag (`RecordStaffDashboardController::tag`)**: Parallel official enrollment tagging by registrar.
   - **Evaluator Pass (`EvaluatorDashboardController::passApplication`)**: Parallel document evaluation approval to grade evaluation stage.

#### Real Multi-Process Before vs. After Concurrency Comparison Table

| Action / Test Parameter | Baseline Metric (`8326deb4^` / `ade6ab1a`) | Fixed Metric (`panel-fixes/aj-38-workflow-concurrency`) | Outcome / Status |
| :--- | :---: | :---: | :---: |
| **Interviewer Accept: Double 200s** | **20 / 20 (100% race failure)** | **0 / 20 (0% race failure)** | **RESOLVED** |
| **Interviewer Accept: Clean Passes (1x 200 & 1x 409)** | **0 / 20 (0%)** | **20 / 20 (100%)** | **RESOLVED** |
| **Interviewer Accept: Final Program Slots** | **10** (Net change: -40 vs expected -20; **20 double decrements**) | **30** (Net change: -20 vs expected -20; **0 leaks**) | **RESOLVED** |
| **Interviewer Accept: Duplicate Medical Processes** | **20 duplicate sets** (every applicant had 2 rows) | **0 rows** (`[]`) | **RESOLVED** |
| **Registrar Tag: Double 200s** | **20 / 20 (100% race failure)** | **0 / 20 (0% race failure)** | **RESOLVED** |
| **Registrar Tag: Clean Passes (1x 200 & 1x 409)** | **0 / 20 (0%)** | **20 / 20 (100%)** | **RESOLVED** |
| **Registrar Tag: Duplicate Audit Log Sets** | **20 duplicate sets** (each applicant logged twice) | **0 duplicate sets** (`0`) | **RESOLVED** |
| **Registrar Tag: Final Enrollment Status** | 20 / 20 `officially_enrolled` | 20 / 20 `officially_enrolled` | Correct final state |
| **Evaluator Pass: Double 200s** | **20 / 20 (100% race failure)** | **0 / 20 (0% race failure)** | **RESOLVED** |
| **Evaluator Pass: Clean Passes (1x 200 & 1x 409)** | **0 / 20 (0%)** | **20 / 20 (100%)** | **RESOLVED** |
| **Evaluator Pass: Duplicate Next-Stage Rows** | **20 duplicate sets** (2 grade evaluator rows each) | **0 rows** (`[]`) | **RESOLVED** |
| **Server 500 Error Count (All Runs)** | **0** | **0** | Zero crashes / zero deadlocks |
| **Database Concurrency Mechanism** | ❌ Checks outside transaction; no row lock | ✅ `lockForUpdate()` + in-transaction guard re-check | Atomically serialized |

*Staging Execution Note:* While local Windows development required OS-level CLI processes to produce parallel execution due to single-threaded `php artisan serve`, production/staging environments running PHP-FPM or Nginx naturally spawn multiple worker processes. The in-transaction pessimistic lock (`lockForUpdate()`) serializes the guarded execution paths: under concurrent execution, the second worker blocks on the locked row until the first worker commits, reads the updated state, and cleanly returns an HTTP 409 Conflict. Staging verification is scheduled once Railway service is restored.

---

## 6. Limitations / Open Gaps

### Gap 1: Missing Debounce Utility on Frontend Submit Click
- **Severity:** Low
- **Description:** While `ApplicationReviewModal.vue` uses `submitting.value = true` and immediately hides the confirmation modal, it does not employ a formal debounce function (`lodash.debounce`). On low-end mobile devices where DOM re-rendering is delayed, rapid taps could theoretically dispatch multiple network requests before Vue unmounts the modal.
- **Mitigation:** Even if multiple network requests fire, backend pessimistic row locking guarantees that only the first request is processed.

---

## 7. Verdict: Verified for applicant submission under load; workflow actions verified under real multi-process concurrency on local MySQL (interviewer accept, registrar tag, evaluator pass), staging run pending
Applicant submission is verified under load on Railway Staging (dropping duplicate submissions from 14/20 to 0/20). The real OS-level multi-process before/after concurrency test on local MySQL covers interviewer accept, registrar tag, and evaluator pass across $N=20$ rounds each with an injected 300 ms stress window:
- In the baseline code (`8326deb4^` / `ade6ab1a`), all three actions failed in 20/20 rounds with two 200s, generating 20 duplicate quota decrements (interviewer accept), 20 duplicate audit log sets (registrar tag), and 20 duplicate next-stage process rows (evaluator pass).
- In the fixed code (`panel-fixes/aj-38-workflow-concurrency`), pessimistic row locking (`lockForUpdate()`) serialized the guarded paths in 20/20 rounds (0/20 double 200s, 20/20 1x 200 and 1x 409 clean passes), with zero slot leaks, zero duplicate audit logs, and zero duplicate next-stage processes.
Every other workflow action (interviewer reject, evaluator reject, evaluator return, untag, change course) uses the identical in-transaction `lockForUpdate()` pattern and is verified sequentially via Pest SQLite (`WorkflowConcurrencyTest`, 8 tests, 36 assertions, 0 failures). Staging multi-worker concurrency verification remains pending until Railway staging is restored.

