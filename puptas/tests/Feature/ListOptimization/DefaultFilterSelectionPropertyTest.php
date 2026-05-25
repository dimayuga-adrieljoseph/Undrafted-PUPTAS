<?php

use App\Models\TestPasser;
use App\Models\PasserStatus;
use App\Models\User;

/**
 * Property-based test for default filter selection.
 *
 * Feature: list-optimization
 * Property 6: Default filter selection
 * **Validates: Requirements 3.5**
 *
 * For any non-empty dataset where no school_year filter is provided,
 * the system SHALL default to the school_year with the maximum value (most recent),
 * and the first available batch_number within that school_year.
 */

beforeEach(function () {
    // Ensure passer_statuses exist for factory
    PasserStatus::firstOrCreate(['id' => 1], ['status' => 'qualified']);
    PasserStatus::firstOrCreate(['id' => 2], ['status' => 'waitlisted']);
    PasserStatus::firstOrCreate(['id' => 3], ['status' => 'unqualified']);
});

// Generate random school_year sets for property testing
$defaultFilterCases = [];
$seed = 42;

// Generate at least 10 iterations (use propertyTestIterations for consistency)
$iterations = max(10, propertyTestIterations());

for ($i = 0; $i < $iterations; $i++) {
    $seed = ($seed * 1103515245 + 12345) & 0x7fffffff;

    // Generate 2-5 distinct school years
    $numYears = ($seed % 4) + 2;
    $seed = ($seed * 1103515245 + 12345) & 0x7fffffff;

    $baseYear = 2018 + ($seed % 6); // Start between 2018-2023
    $schoolYears = [];
    for ($j = 0; $j < $numYears; $j++) {
        $startYear = $baseYear + $j;
        $schoolYears[] = $startYear . '-' . ($startYear + 1);
    }

    // Generate batch numbers for the max school year (1-4 batches)
    $seed = ($seed * 1103515245 + 12345) & 0x7fffffff;
    $numBatches = ($seed % 4) + 1;
    $batchNumbers = [];
    for ($b = 1; $b <= $numBatches; $b++) {
        $batchNumbers[] = (string) $b;
    }

    // Shuffle batch numbers to ensure ordering is tested
    $seed = ($seed * 1103515245 + 12345) & 0x7fffffff;
    $shuffledBatches = $batchNumbers;
    // Simple deterministic shuffle
    for ($k = count($shuffledBatches) - 1; $k > 0; $k--) {
        $seed = ($seed * 1103515245 + 12345) & 0x7fffffff;
        $swapIdx = $seed % ($k + 1);
        [$shuffledBatches[$k], $shuffledBatches[$swapIdx]] = [$shuffledBatches[$swapIdx], $shuffledBatches[$k]];
    }

    $defaultFilterCases[] = [$schoolYears, $shuffledBatches];
}

it(
    'Property 6: Default filter selection - defaults to maximum school_year and first batch_number when no filter provided',
    function (array $schoolYears, array $batchNumbersForMaxYear) {
        // Determine expected defaults
        $maxSchoolYear = max($schoolYears);
        $expectedBatchNumber = min($batchNumbersForMaxYear); // first when ordered ascending

        // Create records for non-max school years with arbitrary batch numbers
        foreach ($schoolYears as $sy) {
            if ($sy !== $maxSchoolYear) {
                TestPasser::factory()->create([
                    'school_year' => $sy,
                    'batch_number' => '1',
                ]);
            }
        }

        // Create records for the max school year with the specified batch numbers
        foreach ($batchNumbersForMaxYear as $bn) {
            TestPasser::factory()->create([
                'school_year' => $maxSchoolYear,
                'batch_number' => $bn,
            ]);
        }

        // Authenticate as admin (role_id = 2)
        $admin = User::factory()->create(['role_id' => 2]);

        // Make GET request WITHOUT school_year filter
        $response = $this->actingAs($admin)
            ->get('/test-passers');

        $response->assertStatus(200);

        // Extract the filters prop from the Inertia response
        $page = $response->viewData('page');
        $filters = $page['props']['filters'];

        // Assert: system defaults to the maximum school_year
        expect($filters['school_year'])->toBe(
            $maxSchoolYear,
            "Expected default school_year to be '{$maxSchoolYear}' (maximum), got '{$filters['school_year']}'"
        );

        // Assert: first available batch_number within that school_year is selected
        expect($filters['batch_number'])->toBe(
            $expectedBatchNumber,
            "Expected default batch_number to be '{$expectedBatchNumber}' (first ascending), got '{$filters['batch_number']}'"
        );
    }
)->with($defaultFilterCases);
