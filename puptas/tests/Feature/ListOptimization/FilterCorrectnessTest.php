<?php

use App\Models\TestPasser;
use App\Models\PasserStatus;
use App\Models\User;

/**
 * Property-based test: Filter correctness (AND logic)
 *
 * Feature: list-optimization
 * Property 1: Filter correctness
 *
 * For any combination of filter parameters (school_year, batch_number, strand, passer_status_id)
 * applied to any dataset of test passers, every record in the returned result set SHALL satisfy
 * ALL applied filter conditions simultaneously.
 *
 * **Validates: Requirements 3.2, 3.3, 4.1, 4.2, 4.3, 4.4, 4.6**
 */

beforeEach(function () {
    // Ensure passer_statuses exist for factory
    PasserStatus::firstOrCreate(['id' => 1], ['status' => 'qualified']);
    PasserStatus::firstOrCreate(['id' => 2], ['status' => 'waitlisted']);
    PasserStatus::firstOrCreate(['id' => 3], ['status' => 'unqualified']);

    // Create an admin user for authentication
    $this->adminUser = User::factory()->create(['role_id' => 2]);
});

// Generate random filter combinations for property testing
$filterCombinations = [];
$schoolYears = ['2022-2023', '2023-2024', '2024-2025', '2025-2026'];
$batchNumbers = ['1', '2', '3', '4'];
$strands = ['STEM', 'ABM', 'HUMSS', 'GAS'];
$statusIds = [1, 2, 3];

for ($i = 0; $i < max(10, propertyTestIterations()); $i++) {
    // Randomly decide which filters to apply (at least one)
    $filters = [];

    if (rand(0, 1)) {
        $filters['school_year'] = $schoolYears[array_rand($schoolYears)];
    }
    if (rand(0, 1)) {
        $filters['batch_number'] = $batchNumbers[array_rand($batchNumbers)];
    }
    if (rand(0, 1)) {
        $filters['strand'] = $strands[array_rand($strands)];
    }
    if (rand(0, 1)) {
        $filters['status'] = $statusIds[array_rand($statusIds)];
    }

    // Ensure at least one filter is applied
    if (empty($filters)) {
        $filters['school_year'] = $schoolYears[array_rand($schoolYears)];
    }

    $filterCombinations[] = [$filters];
}

it(
    'Property 1: Filter correctness (AND logic) - every returned record satisfies ALL applied filter conditions simultaneously',
    function (array $filters) {
        // Seed a varied dataset of test passers
        $schoolYears = ['2022-2023', '2023-2024', '2024-2025', '2025-2026'];
        $batchNumbers = ['1', '2', '3', '4'];
        $strands = ['STEM', 'ABM', 'HUMSS', 'GAS'];
        $statusIds = [1, 2, 3];

        // Create 20 records with randomized attributes to ensure variety
        for ($j = 0; $j < 20; $j++) {
            TestPasser::factory()->create([
                'school_year' => $schoolYears[array_rand($schoolYears)],
                'batch_number' => $batchNumbers[array_rand($batchNumbers)],
                'strand' => $strands[array_rand($strands)],
                'passer_status_id' => $statusIds[array_rand($statusIds)],
            ]);
        }

        // Build query parameters for the HTTP request
        $queryParams = $filters;

        // Make authenticated GET request to the test passers index endpoint
        $response = $this->actingAs($this->adminUser)
            ->get('/test-passers?' . http_build_query($queryParams));

        $response->assertStatus(200);

        // Extract the paginated passers from the Inertia response
        $page = $response->viewData('page');
        $passers = $page['props']['passers']['data'] ?? [];

        // Assert every returned record satisfies ALL applied filter conditions
        foreach ($passers as $passer) {
            if (isset($filters['school_year'])) {
                expect($passer['school_year'])->toBe(
                    $filters['school_year'],
                    "Record with id {$passer['test_passer_id']} has school_year '{$passer['school_year']}' but filter requires '{$filters['school_year']}'"
                );
            }

            if (isset($filters['batch_number'])) {
                expect($passer['batch_number'])->toBe(
                    $filters['batch_number'],
                    "Record with id {$passer['test_passer_id']} has batch_number '{$passer['batch_number']}' but filter requires '{$filters['batch_number']}'"
                );
            }

            if (isset($filters['strand'])) {
                expect($passer['strand'])->toBe(
                    $filters['strand'],
                    "Record with id {$passer['test_passer_id']} has strand '{$passer['strand']}' but filter requires '{$filters['strand']}'"
                );
            }

            if (isset($filters['status'])) {
                expect($passer['passer_status_id'])->toBe(
                    $filters['status'],
                    "Record with id {$passer['test_passer_id']} has passer_status_id '{$passer['passer_status_id']}' but filter requires '{$filters['status']}'"
                );
            }
        }
    }
)->with($filterCombinations);
