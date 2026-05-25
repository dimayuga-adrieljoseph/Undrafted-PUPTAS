<?php

use App\Models\TestPasser;
use App\Models\PasserStatus;
use App\Models\User;

/**
 * Property-based test for filter options correctness.
 *
 * Feature: list-optimization
 * Property 7: Filter options correctness
 * **Validates: Requirements 5.1, 5.2**
 *
 * For any dataset, the returned `schoolYears` array SHALL contain exactly the distinct
 * non-null school_year values present in the database sorted in descending order, and
 * the returned `batchNumbers` array SHALL contain exactly the distinct non-null
 * batch_number values for the currently selected school_year.
 */

beforeEach(function () {
    PasserStatus::firstOrCreate(['id' => 1], ['status' => 'qualified']);
    PasserStatus::firstOrCreate(['id' => 2], ['status' => 'waitlisted']);
    PasserStatus::firstOrCreate(['id' => 3], ['status' => 'unqualified']);
});

// Generate random datasets with varied school_year and batch_number values (including nulls)
$datasets = [];
$schoolYearPool = ['2020-2021', '2021-2022', '2022-2023', '2023-2024', '2024-2025', '2025-2026'];
$batchNumberPool = ['1', '2', '3', '4', '5', null];

for ($i = 0; $i < max(10, propertyTestIterations()); $i++) {
    $records = [];
    $recordCount = rand(3, 12);

    for ($j = 0; $j < $recordCount; $j++) {
        $records[] = [
            'school_year' => $schoolYearPool[array_rand($schoolYearPool)],
            'batch_number' => $batchNumberPool[array_rand($batchNumberPool)],
        ];
    }

    $datasets[] = [$records];
}

it('Property 7: Filter options correctness - schoolYears contains distinct non-null values sorted descending and batchNumbers contains distinct non-null values for selected school_year', function (array $records) {
    // Arrange: create an admin user
    $user = User::factory()->create(['role_id' => 2]);

    // Create TestPasser records with the generated school_year/batch_number combinations
    foreach ($records as $record) {
        TestPasser::factory()->create([
            'school_year' => $record['school_year'],
            'batch_number' => $record['batch_number'],
        ]);
    }

    // Compute expected values from the dataset
    $expectedSchoolYears = collect($records)
        ->pluck('school_year')
        ->filter(fn ($v) => $v !== null)
        ->unique()
        ->sortDesc()
        ->values()
        ->all();

    // The default selected school_year is the maximum (most recent)
    $selectedSchoolYear = !empty($expectedSchoolYears) ? $expectedSchoolYears[0] : null;

    // Expected batch numbers: distinct non-null batch_number values for the selected school_year
    $expectedBatchNumbers = collect($records)
        ->filter(fn ($r) => $r['school_year'] === $selectedSchoolYear && $r['batch_number'] !== null)
        ->pluck('batch_number')
        ->unique()
        ->sort()
        ->values()
        ->all();

    // Act: make GET request to the index endpoint (no school_year filter = defaults to most recent)
    $response = $this->actingAs($user)->get('/test-passers');

    $response->assertStatus(200);

    // Extract Inertia page props
    $page = $response->viewData('page');
    $filterOptions = $page['props']['filterOptions'];

    // Assert: schoolYears contains exactly the distinct non-null school_year values sorted descending
    expect($filterOptions['schoolYears'])->toBe($expectedSchoolYears,
        'schoolYears should contain exactly the distinct non-null school_year values sorted descending');

    // Assert: batchNumbers contains exactly the distinct non-null batch_number values for the selected school_year
    expect($filterOptions['batchNumbers'])->toBe($expectedBatchNumbers,
        'batchNumbers should contain exactly the distinct non-null batch_number values for the selected school_year');
})->with($datasets);

// Additional test: verify batchNumbers correctness when a specific school_year is selected
$specificSchoolYearDatasets = [];
for ($i = 0; $i < max(10, propertyTestIterations()); $i++) {
    $records = [];
    $recordCount = rand(5, 15);

    for ($j = 0; $j < $recordCount; $j++) {
        $records[] = [
            'school_year' => $schoolYearPool[array_rand($schoolYearPool)],
            'batch_number' => $batchNumberPool[array_rand($batchNumberPool)],
        ];
    }

    // Pick a random school_year from the records to use as the filter
    $nonNullSchoolYears = collect($records)->pluck('school_year')->filter()->unique()->values()->all();
    $selectedYear = !empty($nonNullSchoolYears) ? $nonNullSchoolYears[array_rand($nonNullSchoolYears)] : null;

    $specificSchoolYearDatasets[] = [$records, $selectedYear];
}

it('Property 7: Filter options correctness - batchNumbers matches distinct non-null values for explicitly selected school_year', function (array $records, ?string $selectedSchoolYear) {
    // Arrange
    $user = User::factory()->create(['role_id' => 2]);

    foreach ($records as $record) {
        TestPasser::factory()->create([
            'school_year' => $record['school_year'],
            'batch_number' => $record['batch_number'],
        ]);
    }

    // Expected schoolYears: always all distinct non-null values sorted descending
    $expectedSchoolYears = collect($records)
        ->pluck('school_year')
        ->filter(fn ($v) => $v !== null)
        ->unique()
        ->sortDesc()
        ->values()
        ->all();

    // Expected batchNumbers for the explicitly selected school_year
    $expectedBatchNumbers = collect($records)
        ->filter(fn ($r) => $r['school_year'] === $selectedSchoolYear && $r['batch_number'] !== null)
        ->pluck('batch_number')
        ->unique()
        ->sort()
        ->values()
        ->all();

    // Act: make GET request with explicit school_year filter
    $response = $this->actingAs($user)->get('/test-passers?school_year=' . urlencode($selectedSchoolYear));

    $response->assertStatus(200);

    // Extract Inertia page props
    $page = $response->viewData('page');
    $filterOptions = $page['props']['filterOptions'];

    // Assert: schoolYears still contains all distinct non-null values sorted descending
    expect($filterOptions['schoolYears'])->toBe($expectedSchoolYears,
        'schoolYears should contain all distinct non-null school_year values regardless of selected filter');

    // Assert: batchNumbers contains exactly the distinct non-null batch_number values for the selected school_year
    expect($filterOptions['batchNumbers'])->toBe($expectedBatchNumbers,
        'batchNumbers should contain exactly the distinct non-null batch_number values for school_year: ' . $selectedSchoolYear);
})->with($specificSchoolYearDatasets);
