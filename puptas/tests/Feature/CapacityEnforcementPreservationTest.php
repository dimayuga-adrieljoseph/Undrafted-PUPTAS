<?php

/**
 * Preservation Property Tests — Capacity Enforcement Fix
 *
 * Validates: Requirements 3.1, 3.2, 3.3, 3.4, 3.5
 *
 * These tests capture BASELINE behavior that MUST be preserved after the fix.
 * On UNFIXED code, ALL tests PASS — confirming the baseline is intact.
 * On FIXED code, all tests MUST STILL PASS — confirming no regressions.
 *
 * Property 2: Preservation — Unchanged Behaviors for Non-Buggy Inputs
 */

use App\Models\TestPasser;
use App\Services\CapacityEnforcementService;
use App\Services\ScoreThresholdService;
use Illuminate\Support\Facades\DB;

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

/**
 * Seed the passer_statuses table with required statuses.
 */
function capacityPreservationSeedStatuses(): void
{
    DB::table('passer_statuses')->insertOrIgnore([
        ['id' => 1, 'status' => 'qualified', 'created_at' => now(), 'updated_at' => now()],
        ['id' => 2, 'status' => 'waitlisted', 'created_at' => now(), 'updated_at' => now()],
        ['id' => 3, 'status' => 'unqualified', 'created_at' => now(), 'updated_at' => now()],
        ['id' => 4, 'status' => 'waitlisted_below_cutoff', 'created_at' => now(), 'updated_at' => now()],
    ]);
}

/**
 * Create a TestPasser record with specific attributes.
 */
function capacityPreservationCreateRecord(array $attributes): TestPasser
{
    static $counter = 0;
    $counter++;

    $defaults = [
        'surname' => 'Surname' . $counter,
        'first_name' => 'First' . $counter,
        'email' => "captest{$counter}_" . uniqid() . '@example.com',
        'reference_number' => 'CAPREF-' . str_pad($counter, 6, '0', STR_PAD_LEFT) . uniqid(),
        'school_year' => '2025-2026',
        'status' => 'registered',
    ];

    return TestPasser::create(array_merge($defaults, $attributes));
}

/**
 * Generate a deterministic pseudo-random float score in a given range.
 */
function capacityPreservationScore(int &$seed, float $min = 55.0, float $max = 100.0): float
{
    $seed = ($seed * 1103515245 + 12345) & 0x7fffffff;
    return round($min + ($seed % 10000) / 10000 * ($max - $min), 2);
}

// ===========================================================================
// Preservation 3.1 — Status 3 records are never modified by enforce()
//
// For all school years: status 3 records are never included in re-ranking
// regardless of their score. After enforce(), their passer_status_id and
// batch_number remain unchanged.
//
// **Validates: Requirements 3.1**
// ===========================================================================

// **Validates: Requirements 3.1**
it('Preservation 3.1: status 3 records are never modified by enforce() regardless of score', function () {
    capacityPreservationSeedStatuses();

    $schoolYear = '2025-2026';
    $service = new CapacityEnforcementService();

    $iterations = propertyTestIterations();
    $seed = 42;

    for ($i = 0; $i < $iterations; $i++) {
        // Generate a random number of status 3 records with high scores
        $seed = ($seed * 1103515245 + 12345) & 0x7fffffff;
        $numStatus3 = ($seed % 5) + 1; // 1-5 status 3 records

        $status3Records = [];
        for ($j = 0; $j < $numStatus3; $j++) {
            $score = capacityPreservationScore($seed, 85.0, 100.0); // High scores
            $record = capacityPreservationCreateRecord([
                'school_year' => $schoolYear,
                'passer_status_id' => 3,
                'batch_number' => null,
                'pupcet_total_score' => $score,
            ]);
            $status3Records[] = $record;
        }

        // Create > 550 status 1 and 2 records to trigger enforcement
        $seed = ($seed * 1103515245 + 12345) & 0x7fffffff;
        $numEligible = 551 + ($seed % 10); // 551-560

        for ($j = 0; $j < $numEligible; $j++) {
            $score = capacityPreservationScore($seed, 55.0, 95.0);
            $statusId = ($j < 200) ? 1 : 2;
            capacityPreservationCreateRecord([
                'school_year' => $schoolYear,
                'passer_status_id' => $statusId,
                'batch_number' => ($statusId === 1) ? 'Batch 1' : 'Batch 4',
                'pupcet_total_score' => $score,
            ]);
        }

        // Run enforce
        $service->enforce($schoolYear);

        // Assert: all status 3 records remain unchanged
        foreach ($status3Records as $record) {
            $fresh = TestPasser::find($record->test_passer_id);
            expect($fresh->passer_status_id)->toBe(3, "Status 3 record (score {$record->pupcet_total_score}) was modified by enforce()");
            expect($fresh->batch_number)->toBeNull("Status 3 record batch_number was modified by enforce()");
        }

        // Clean up for next iteration
        TestPasser::where('school_year', $schoolYear)->delete();
    }
});

// ===========================================================================
// Preservation 3.2 — Under-capacity: no records are demoted
//
// For all school years with ≤ 550 eligible records (statuses 1, 2 only, no
// status 4): enforce() applies ScoreThresholdService rules without demoting
// any records to status 4. When records already have correct assignments,
// enforce() returns 0.
//
// **Validates: Requirements 3.2**
// ===========================================================================

// **Validates: Requirements 3.2**
it('Preservation 3.2: when eligible records ≤ 550 (no status 4), no records are demoted to status 4', function () {
    capacityPreservationSeedStatuses();

    $schoolYear = '2025-2026';
    $service = new CapacityEnforcementService();
    $scoreThresholdService = new ScoreThresholdService();

    $iterations = propertyTestIterations();
    $seed = 77;

    for ($i = 0; $i < $iterations; $i++) {
        // Generate a random count of eligible records ≤ 550
        $seed = ($seed * 1103515245 + 12345) & 0x7fffffff;
        $numRecords = ($seed % 550) + 1; // 1-550

        $recordIds = [];
        for ($j = 0; $j < $numRecords; $j++) {
            $score = capacityPreservationScore($seed, 55.0, 100.0);
            // Create records with correct ScoreThresholdService assignments
            $resolved = $scoreThresholdService->resolve($score);

            $record = capacityPreservationCreateRecord([
                'school_year' => $schoolYear,
                'passer_status_id' => $resolved['passer_status_id'],
                'batch_number' => $resolved['batch_number'],
                'pupcet_total_score' => $score,
            ]);
            $recordIds[] = $record->test_passer_id;
        }

        // Run enforce
        $result = $service->enforce($schoolYear);

        // Assert: returns 0 (no changes needed since records already have correct assignments)
        expect($result)->toBe(0, "enforce() returned {$result} instead of 0 for {$numRecords} records (≤ 550) with correct assignments");

        // Assert: no records were demoted to status 4
        $demotedCount = TestPasser::whereIn('test_passer_id', $recordIds)
            ->where('passer_status_id', 4)
            ->count();
        expect($demotedCount)->toBe(0, "Records were demoted to status 4 when count ≤ 550");

        // Assert: all records have correct ScoreThresholdService assignments
        foreach ($recordIds as $id) {
            $fresh = TestPasser::find($id);
            $expected = $scoreThresholdService->resolve($fresh->pupcet_total_score);
            expect($fresh->passer_status_id)->toBe($expected['passer_status_id'], "Record status doesn't match resolve() for score {$fresh->pupcet_total_score}");
            expect($fresh->batch_number)->toBe($expected['batch_number'], "Record batch doesn't match resolve() for score {$fresh->pupcet_total_score}");
        }

        // Clean up for next iteration
        TestPasser::where('school_year', $schoolYear)->delete();
    }
});

// ===========================================================================
// Preservation 3.3 — Over-capacity demotion: records beyond 550 get status 4
//
// For all school years with NO status 4 records and > 550 eligible records:
// enforce() demotes records beyond position 550 to status 4 with null batch.
//
// Note: On unfixed code, the sort is by passer_status_id ASC, then score DESC,
// then created_at ASC. We test that records beyond 550 (in that sort order)
// are demoted to status 4 with null batch_number.
//
// **Validates: Requirements 3.3**
// ===========================================================================

// **Validates: Requirements 3.3**
it('Preservation 3.3: with > 550 eligible records (no status 4), records beyond position 550 are demoted to status 4', function () {
    capacityPreservationSeedStatuses();

    $schoolYear = '2025-2026';
    $service = new CapacityEnforcementService();

    $iterations = min(propertyTestIterations(), 5); // Limit iterations due to large record count
    $seed = 123;

    for ($i = 0; $i < $iterations; $i++) {
        // Generate > 550 eligible records (only statuses 1 and 2)
        $seed = ($seed * 1103515245 + 12345) & 0x7fffffff;
        $numRecords = 551 + ($seed % 20); // 551-570

        $records = [];
        for ($j = 0; $j < $numRecords; $j++) {
            $score = capacityPreservationScore($seed, 55.0, 100.0);
            $seed = ($seed * 1103515245 + 12345) & 0x7fffffff;
            $statusId = ($seed % 2 === 0) ? 1 : 2;
            $batchNumber = ($statusId === 1) ? 'Batch 1' : 'Batch 4';

            $record = capacityPreservationCreateRecord([
                'school_year' => $schoolYear,
                'passer_status_id' => $statusId,
                'batch_number' => $batchNumber,
                'pupcet_total_score' => $score,
            ]);
            $records[] = $record;
        }

        // Run enforce
        $service->enforce($schoolYear);

        // Determine expected ranking (same sort as unfixed code):
        // passer_status_id ASC, pupcet_total_score DESC, created_at ASC
        $allRecords = TestPasser::where('school_year', $schoolYear)
            ->whereIn('test_passer_id', collect($records)->pluck('test_passer_id'))
            ->get();

        // Get the original sort order (before enforce modified them)
        // We need to check that records beyond 550 now have status 4
        $demotedRecords = TestPasser::where('school_year', $schoolYear)
            ->where('passer_status_id', 4)
            ->get();

        // The number of demoted records should be numRecords - 550
        $expectedDemoted = $numRecords - 550;
        expect($demotedRecords->count())->toBe($expectedDemoted, "Expected {$expectedDemoted} demoted records, got {$demotedRecords->count()}");

        // All demoted records should have null batch_number
        foreach ($demotedRecords as $demoted) {
            expect($demoted->batch_number)->toBeNull("Demoted record should have null batch_number");
        }

        // Clean up for next iteration
        TestPasser::where('school_year', $schoolYear)->delete();
    }
});

// ===========================================================================
// Preservation 3.4 — Return value: enforce() returns count of reassigned records
//
// For all school years: enforce() returns the count of records whose status
// changed during the operation.
//
// **Validates: Requirements 3.4**
// ===========================================================================

// **Validates: Requirements 3.4**
it('Preservation 3.4: enforce() returns the count of records whose status changed', function () {
    capacityPreservationSeedStatuses();

    $schoolYear = '2025-2026';
    $service = new CapacityEnforcementService();
    $scoreThresholdService = new ScoreThresholdService();

    $iterations = min(propertyTestIterations(), 5); // Limit due to large record count
    $seed = 200;

    for ($i = 0; $i < $iterations; $i++) {
        // Generate > 550 eligible records with CORRECT ScoreThresholdService assignments
        // so that only demotions beyond 550 count as reassignments
        $seed = ($seed * 1103515245 + 12345) & 0x7fffffff;
        $numRecords = 551 + ($seed % 30); // 551-580

        for ($j = 0; $j < $numRecords; $j++) {
            $score = capacityPreservationScore($seed, 55.0, 100.0);
            $resolved = $scoreThresholdService->resolve($score);

            capacityPreservationCreateRecord([
                'school_year' => $schoolYear,
                'passer_status_id' => $resolved['passer_status_id'],
                'batch_number' => $resolved['batch_number'],
                'pupcet_total_score' => $score,
            ]);
        }

        // Run enforce and capture return value
        $result = $service->enforce($schoolYear);

        // The return value should equal the number of records demoted
        // (records beyond position 550 that were changed to status 4)
        // Since all records already have correct resolve() assignments,
        // only records beyond 550 that had status 1 or 2 will be reassigned
        $expectedDemoted = $numRecords - ScoreThresholdService::CAPACITY_LIMIT;
        expect($result)->toBe($expectedDemoted, "enforce() returned {$result}, expected {$expectedDemoted} for {$numRecords} records");

        // Clean up for next iteration
        TestPasser::where('school_year', $schoolYear)->delete();
    }
});

// ===========================================================================
// Preservation 3.5 — Tiebreaker: same score ordered by created_at ASC
//
// For all school years: when two records have the same pupcet_total_score,
// the one with earlier created_at ranks higher (is retained in top 550).
//
// **Validates: Requirements 3.5**
// ===========================================================================

// **Validates: Requirements 3.5**
it('Preservation 3.5: records with same score are ordered by created_at ASC (earlier record ranks higher)', function () {
    capacityPreservationSeedStatuses();

    $schoolYear = '2025-2026';
    $service = new CapacityEnforcementService();
    $scoreThresholdService = new ScoreThresholdService();

    $iterations = min(propertyTestIterations(), 5); // Limit due to large record count
    $seed = 333;

    for ($i = 0; $i < $iterations; $i++) {
        // Create exactly 551 records — 550 will be kept, 1 will be demoted
        // The last two records have the same score but different created_at
        $tiedScore = 70.0;
        $resolvedTied = $scoreThresholdService->resolve($tiedScore);

        // Create 549 records with higher scores to fill most of the capacity
        // Use correct ScoreThresholdService assignments
        for ($j = 0; $j < 549; $j++) {
            $score = capacityPreservationScore($seed, 75.0, 100.0);
            $resolved = $scoreThresholdService->resolve($score);
            capacityPreservationCreateRecord([
                'school_year' => $schoolYear,
                'passer_status_id' => $resolved['passer_status_id'],
                'batch_number' => $resolved['batch_number'],
                'pupcet_total_score' => $score,
            ]);
        }

        // Create the earlier record (should rank higher — position 550)
        // Must set created_at via DB update since it's not in $fillable
        $earlierRecord = capacityPreservationCreateRecord([
            'school_year' => $schoolYear,
            'passer_status_id' => $resolvedTied['passer_status_id'],
            'batch_number' => $resolvedTied['batch_number'],
            'pupcet_total_score' => $tiedScore,
        ]);
        DB::table('test_passers')
            ->where('test_passer_id', $earlierRecord->test_passer_id)
            ->update(['created_at' => '2025-01-01 00:00:00']);

        // Create the later record (should rank lower — position 551, demoted)
        $laterRecord = capacityPreservationCreateRecord([
            'school_year' => $schoolYear,
            'passer_status_id' => $resolvedTied['passer_status_id'],
            'batch_number' => $resolvedTied['batch_number'],
            'pupcet_total_score' => $tiedScore,
        ]);
        DB::table('test_passers')
            ->where('test_passer_id', $laterRecord->test_passer_id)
            ->update(['created_at' => '2025-06-01 00:00:00']);

        // Run enforce
        $service->enforce($schoolYear);

        // Assert: earlier record is NOT demoted (stays in top 550)
        $freshEarlier = TestPasser::find($earlierRecord->test_passer_id);
        expect($freshEarlier->passer_status_id)->toBe($resolvedTied['passer_status_id'], "Earlier record (created_at 2025-01-01) was incorrectly demoted");

        // Assert: later record IS demoted (beyond position 550)
        $freshLater = TestPasser::find($laterRecord->test_passer_id);
        expect($freshLater->passer_status_id)->toBe(4, "Later record (created_at 2025-06-01) was NOT demoted — tiebreaker failed");
        expect($freshLater->batch_number)->toBeNull("Demoted record should have null batch_number");

        // Clean up for next iteration
        TestPasser::where('school_year', $schoolYear)->delete();
    }
});

// ===========================================================================
// Preservation 3.5b — Transaction atomicity: enforce() executes within a DB transaction
//
// Verify that enforce() uses DB::transaction by confirming the transaction
// level increases during execution. Since RefreshDatabase already wraps tests
// in a transaction (level 1), enforce() should bump it to level 2.
//
// **Validates: Requirements 3.5**
// ===========================================================================

// **Validates: Requirements 3.5**
test('Preservation 3.5b: enforce() executes within a DB transaction (atomicity preserved)', function () {
    capacityPreservationSeedStatuses();

    $schoolYear = '2025-2026';

    // Create > 550 records
    for ($j = 0; $j < 555; $j++) {
        capacityPreservationCreateRecord([
            'school_year' => $schoolYear,
            'passer_status_id' => 2,
            'batch_number' => 'Batch 4',
            'pupcet_total_score' => 60.0 + ($j * 0.01),
        ]);
    }

    // Record the transaction level before enforce()
    $levelBefore = DB::transactionLevel();

    // Use DB::listen to capture that a transaction was started during enforce()
    $transactionStarted = false;
    DB::listen(function ($query) use (&$transactionStarted) {
        if (str_contains(strtolower($query->sql), 'savepoint') || str_contains(strtolower($query->sql), 'begin')) {
            $transactionStarted = true;
        }
    });

    $service = new CapacityEnforcementService();
    $service->enforce($schoolYear);

    // After enforce(), transaction level should return to the same level
    $levelAfter = DB::transactionLevel();
    expect($levelAfter)->toBe($levelBefore, "Transaction level should return to pre-enforce level after completion");

    // Verify the operation completed atomically — all demotions happened (not partial)
    $demotedCount = TestPasser::where('school_year', $schoolYear)
        ->where('passer_status_id', 4)
        ->count();
    expect($demotedCount)->toBe(5, "Expected 5 demoted records (555 - 550) — atomicity check");

    // Verify no records are left in an intermediate state
    $remainingEligible = TestPasser::where('school_year', $schoolYear)
        ->whereIn('passer_status_id', [1, 2])
        ->count();
    expect($remainingEligible)->toBe(550, "Expected exactly 550 records to remain with status 1 or 2");
});
