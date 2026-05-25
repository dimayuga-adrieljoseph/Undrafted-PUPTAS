<?php

/**
 * Bug Condition Exploration Tests — Capacity Enforcement Fix
 *
 * Validates: Requirements 1.1, 1.2, 1.3, 1.4
 *
 * These tests encode the EXPECTED (correct) behavior.
 * On UNFIXED code, tests FAIL — proving the bug exists:
 *   - Status 4 records are excluded from the re-ranking query
 *   - Higher-scoring status 4 records are never promoted
 *   - ScoreThresholdService::resolve() is not applied to top 550
 *   - enforce() is non-idempotent
 *
 * On FIXED code, all tests PASS.
 */

use App\Models\TestPasser;
use App\Services\CapacityEnforcementService;
use App\Services\ScoreThresholdService;
use Illuminate\Support\Facades\DB;

// ---------------------------------------------------------------------------
// Test 1 — Status 4 records excluded from re-ranking
// Bug condition: status 4 record with score 82.0 should be promoted to
// status 1 / Batch 2 after enforce(), but is excluded from the query
// ---------------------------------------------------------------------------

test('Bug Condition: status 4 record with higher score is promoted after enforce()', function () {
    // Bug condition: isBugCondition(schoolYear) — EXISTS status 4 records with
    // pupcet_total_score > MIN(status 2 records' scores) AND enforce() excludes them
    //
    // On unfixed code, the query only includes passer_status_id IN [1, 2],
    // so Record A (status 4, score 82.0) is never considered for promotion.
    // This assertion FAILS on unfixed code — proving the bug exists.
    //
    // Validates: Requirements 1.1, 1.2, 1.3

    $schoolYear = '2025-2026';

    // Seed passer_statuses
    DB::table('passer_statuses')->insertOrIgnore([
        ['id' => 1, 'status' => 'qualified', 'created_at' => now(), 'updated_at' => now()],
        ['id' => 2, 'status' => 'waitlisted', 'created_at' => now(), 'updated_at' => now()],
        ['id' => 3, 'status' => 'unqualified', 'created_at' => now(), 'updated_at' => now()],
        ['id' => 4, 'status' => 'waitlisted_below_cutoff', 'created_at' => now(), 'updated_at' => now()],
    ]);

    // Record A: high-scoring status 4 record that SHOULD be promoted
    $recordA = TestPasser::factory()->create([
        'school_year' => $schoolYear,
        'pupcet_total_score' => 82.0,
        'passer_status_id' => 4,
        'batch_number' => null,
        'created_at' => now()->subMinutes(100),
    ]);

    // Record B: lower-scoring status 2 record
    $recordB = TestPasser::factory()->create([
        'school_year' => $schoolYear,
        'pupcet_total_score' => 60.0,
        'passer_status_id' => 2,
        'batch_number' => 'Batch 4',
        'created_at' => now()->subMinutes(99),
    ]);

    // Fill remaining slots to exceed 550 total eligible records (statuses 1, 2, 4)
    // We need 549 more records (we already have 2) to reach 551 total
    // Create 549 records with status 2 and scores between 56.0 and 74.0
    $bulkRecords = [];
    for ($i = 0; $i < 549; $i++) {
        $bulkRecords[] = [
            'surname' => 'Filler',
            'first_name' => "Student{$i}",
            'middle_name' => null,
            'email' => "filler{$i}@test.com",
            'school_year' => $schoolYear,
            'pupcet_total_score' => 56.0 + ($i * 0.03), // scores from 56.0 to ~72.47
            'passer_status_id' => 2,
            'batch_number' => 'Batch 4',
            'status' => 'pending',
            'created_at' => now()->subMinutes(50 + $i),
            'updated_at' => now(),
        ];
    }
    DB::table('test_passers')->insert($bulkRecords);

    // Total eligible records: 551 (Record A + Record B + 549 fillers)
    // After enforce(), the top 550 by score should get ScoreThresholdService assignments
    // Record A (score 82.0) should rank near the top and get status 1, Batch 2

    $service = new CapacityEnforcementService();
    $service->enforce($schoolYear);

    // Refresh Record A from database
    $recordA->refresh();

    // Expected: Record A (score 82.0) should be promoted to status 1, Batch 2
    // per ScoreThresholdService rules: 79.00 <= 82.0 < 85.00 → status 1, Batch 2
    expect($recordA->passer_status_id)->toBe(1)
        ->and($recordA->batch_number)->toBe('Batch 2');
});

// ---------------------------------------------------------------------------
// Test 2 — Records beyond position 550 should be demoted to status 4
// ---------------------------------------------------------------------------

test('Bug Condition: records beyond position 550 are demoted to status 4 with null batch', function () {
    // Validates: Requirements 1.1, 1.2

    $schoolYear = '2025-2026';

    // Seed passer_statuses
    DB::table('passer_statuses')->insertOrIgnore([
        ['id' => 1, 'status' => 'qualified', 'created_at' => now(), 'updated_at' => now()],
        ['id' => 2, 'status' => 'waitlisted', 'created_at' => now(), 'updated_at' => now()],
        ['id' => 3, 'status' => 'unqualified', 'created_at' => now(), 'updated_at' => now()],
        ['id' => 4, 'status' => 'waitlisted_below_cutoff', 'created_at' => now(), 'updated_at' => now()],
    ]);

    // Create a status 4 record with high score (should be included in ranking)
    $highScoreStatus4 = TestPasser::factory()->create([
        'school_year' => $schoolYear,
        'pupcet_total_score' => 82.0,
        'passer_status_id' => 4,
        'batch_number' => null,
        'created_at' => now()->subMinutes(200),
    ]);

    // Create 550 status 2 records with scores from 56.0 to 72.0
    $bulkRecords = [];
    for ($i = 0; $i < 550; $i++) {
        $bulkRecords[] = [
            'surname' => 'Filler',
            'first_name' => "Student{$i}",
            'middle_name' => null,
            'email' => "filler{$i}@test.com",
            'school_year' => $schoolYear,
            'pupcet_total_score' => 56.0 + ($i * 0.03), // scores from 56.0 to ~72.5
            'passer_status_id' => 2,
            'batch_number' => 'Batch 4',
            'status' => 'pending',
            'created_at' => now()->subMinutes(100 + $i),
            'updated_at' => now(),
        ];
    }
    DB::table('test_passers')->insert($bulkRecords);

    // Total: 551 eligible records (1 status 4 + 550 status 2)
    // The status 4 record (score 82.0) should rank #1 and be promoted
    // The lowest-scoring status 2 record should be demoted to status 4

    $service = new CapacityEnforcementService();
    $service->enforce($schoolYear);

    // The lowest-scoring record (score 56.0) should be beyond position 550
    $lowestRecord = TestPasser::where('school_year', $schoolYear)
        ->where('pupcet_total_score', 56.0)
        ->first();

    expect($lowestRecord->passer_status_id)->toBe(4)
        ->and($lowestRecord->batch_number)->toBeNull();

    // The status 4 record with score 82.0 should now be promoted (included in ranking)
    $highScoreStatus4->refresh();
    expect($highScoreStatus4->passer_status_id)->toBe(1)
        ->and($highScoreStatus4->batch_number)->toBe('Batch 2');
});

// ---------------------------------------------------------------------------
// Test 3 — Idempotency: enforce() should include status 4 records on every call
// The bug makes enforce() non-idempotent because records demoted to status 4
// are excluded from subsequent runs, AND status 4 records that should be
// promoted are never considered.
// ---------------------------------------------------------------------------

test('Bug Condition: enforce() includes status 4 records and promotes them consistently', function () {
    // Bug condition: On unfixed code, enforce() is non-idempotent because
    // status 4 records are excluded from the query entirely. A status 4 record
    // with a high score should be promoted on EVERY call to enforce(), but on
    // unfixed code it is never promoted.
    //
    // The correct behavior: after enforce(), the status 4 record with score 82.0
    // should be promoted to status 1/Batch 2. Calling enforce() again should
    // produce the same state (return 0 changes).
    //
    // Validates: Requirement 1.4

    $schoolYear = '2025-2026';

    // Seed passer_statuses
    DB::table('passer_statuses')->insertOrIgnore([
        ['id' => 1, 'status' => 'qualified', 'created_at' => now(), 'updated_at' => now()],
        ['id' => 2, 'status' => 'waitlisted', 'created_at' => now(), 'updated_at' => now()],
        ['id' => 3, 'status' => 'unqualified', 'created_at' => now(), 'updated_at' => now()],
        ['id' => 4, 'status' => 'waitlisted_below_cutoff', 'created_at' => now(), 'updated_at' => now()],
    ]);

    // Create a status 4 record with high score — should be promoted
    $status4Record = TestPasser::factory()->create([
        'school_year' => $schoolYear,
        'pupcet_total_score' => 82.0,
        'passer_status_id' => 4,
        'batch_number' => null,
        'created_at' => now()->subMinutes(200),
    ]);

    // Create 555 status 2 records with varying scores
    $bulkRecords = [];
    for ($i = 0; $i < 555; $i++) {
        $bulkRecords[] = [
            'surname' => 'Filler',
            'first_name' => "Student{$i}",
            'middle_name' => null,
            'email' => "filler{$i}@test.com",
            'school_year' => $schoolYear,
            'pupcet_total_score' => 56.0 + ($i * 0.03),
            'passer_status_id' => 2,
            'batch_number' => 'Batch 4',
            'status' => 'pending',
            'created_at' => now()->subMinutes(100 + $i),
            'updated_at' => now(),
        ];
    }
    DB::table('test_passers')->insert($bulkRecords);

    // Total: 556 eligible records (1 status 4 + 555 status 2)

    $service = new CapacityEnforcementService();

    // First call — should promote the status 4 record
    $service->enforce($schoolYear);

    // After first call, the status 4 record should be promoted
    $status4Record->refresh();
    expect($status4Record->passer_status_id)->toBe(1)
        ->and($status4Record->batch_number)->toBe('Batch 2');

    // Second call — should produce no changes (idempotent)
    $result = $service->enforce($schoolYear);

    // After second call, the record should still be status 1/Batch 2
    $status4Record->refresh();
    expect($status4Record->passer_status_id)->toBe(1)
        ->and($status4Record->batch_number)->toBe('Batch 2');

    // Second call should return 0 (no changes needed)
    expect($result)->toBe(0);
});

// ---------------------------------------------------------------------------
// Test 4 — Sort order: status 4 record with score 90.0 ranks above
//           status 2 record with score 56.0
// ---------------------------------------------------------------------------

test('Bug Condition: status 4 record with score 90.0 ranks above status 2 record with score 56.0', function () {
    // Bug condition: On unfixed code, the query sorts by passer_status_id ASC first,
    // so status 2 records always rank above status 4 regardless of score.
    // Additionally, status 4 records are excluded entirely from the query.
    //
    // Validates: Requirements 1.1, 1.2

    $schoolYear = '2025-2026';

    // Seed passer_statuses
    DB::table('passer_statuses')->insertOrIgnore([
        ['id' => 1, 'status' => 'qualified', 'created_at' => now(), 'updated_at' => now()],
        ['id' => 2, 'status' => 'waitlisted', 'created_at' => now(), 'updated_at' => now()],
        ['id' => 3, 'status' => 'unqualified', 'created_at' => now(), 'updated_at' => now()],
        ['id' => 4, 'status' => 'waitlisted_below_cutoff', 'created_at' => now(), 'updated_at' => now()],
    ]);

    // Status 4 record with very high score (should rank #1)
    $highScoreRecord = TestPasser::factory()->create([
        'school_year' => $schoolYear,
        'pupcet_total_score' => 90.0,
        'passer_status_id' => 4,
        'batch_number' => null,
        'created_at' => now()->subMinutes(200),
    ]);

    // Status 2 record with low score (should rank lower)
    $lowScoreRecord = TestPasser::factory()->create([
        'school_year' => $schoolYear,
        'pupcet_total_score' => 56.0,
        'passer_status_id' => 2,
        'batch_number' => 'Batch 4',
        'created_at' => now()->subMinutes(199),
    ]);

    // Fill to exceed 550 total eligible records
    $bulkRecords = [];
    for ($i = 0; $i < 549; $i++) {
        $bulkRecords[] = [
            'surname' => 'Filler',
            'first_name' => "Student{$i}",
            'middle_name' => null,
            'email' => "filler{$i}@test.com",
            'school_year' => $schoolYear,
            'pupcet_total_score' => 57.0 + ($i * 0.03), // scores from 57.0 to ~73.47
            'passer_status_id' => 2,
            'batch_number' => 'Batch 4',
            'status' => 'pending',
            'created_at' => now()->subMinutes(50 + $i),
            'updated_at' => now(),
        ];
    }
    DB::table('test_passers')->insert($bulkRecords);

    // Total: 551 eligible records
    // After enforce(), the status 4 record (score 90.0) should be promoted to status 1, Batch 1
    // per ScoreThresholdService: score >= 85.0 → status 1, Batch 1

    $service = new CapacityEnforcementService();
    $service->enforce($schoolYear);

    $highScoreRecord->refresh();
    $lowScoreRecord->refresh();

    // Status 4 record with score 90.0 should be promoted to Qualified / Batch 1
    expect($highScoreRecord->passer_status_id)->toBe(1)
        ->and($highScoreRecord->batch_number)->toBe('Batch 1');

    // Low score record (56.0) should be demoted to status 4 (it's the lowest, beyond 550)
    expect($lowScoreRecord->passer_status_id)->toBe(4)
        ->and($lowScoreRecord->batch_number)->toBeNull();
});

/*
 * ---------------------------------------------------------------------------
 * Counterexamples documented after running on UNFIXED code
 * ---------------------------------------------------------------------------
 *
 * Test 1 — FAILED: Status 4 record with score 82.0 remains at passer_status_id=4
 *          after enforce() instead of being promoted to passer_status_id=1 / Batch 2.
 *          Counterexample: expect($recordA->passer_status_id)->toBe(1) failed —
 *          actual value was 4. The record was completely excluded from the re-ranking
 *          query because whereIn('passer_status_id', [1, 2]) does not include status 4.
 *
 * Test 2 — FAILED: Lowest-scoring record (score 56.0, status 2) was NOT demoted to
 *          status 4 despite being beyond position 550 when status 4 records are included.
 *          Counterexample: expect($lowestRecord->passer_status_id)->toBe(4) failed —
 *          actual value was 2. Because the status 4 record (score 82.0) was excluded
 *          from the query, only 550 status 2 records were seen, and the count was
 *          exactly at the capacity limit, so no demotions occurred.
 *
 * Test 3 — FAILED: Status 4 record with score 82.0 was NOT promoted after first
 *          enforce() call. Expected passer_status_id=1, got 4.
 *          Counterexample: expect($status4Record->passer_status_id)->toBe(1) failed —
 *          actual value was 4. The enforce() method never considers status 4 records
 *          for promotion, making it impossible for them to re-enter the top 550.
 *          This confirms the non-idempotent design: status 4 records are permanently
 *          excluded regardless of how many times enforce() is called.
 *
 * Test 4 — FAILED: Status 4 record with score 90.0 was NOT promoted to status 1 /
 *          Batch 1 after enforce(). Expected passer_status_id=1, got 4.
 *          Counterexample: expect($highScoreRecord->passer_status_id)->toBe(1) failed —
 *          actual value was 4. Despite having the highest score (90.0), the status 4
 *          record was excluded from the query entirely. The sort order bias
 *          (passer_status_id ASC) is also present but secondary to the exclusion bug.
 *
 * Root Cause Confirmed: The whereIn('passer_status_id', [1, 2]) filter in
 * CapacityEnforcementService::enforce() excludes all status 4 records from
 * re-ranking, preventing higher-scoring applicants from being promoted.
 * ---------------------------------------------------------------------------
 */
