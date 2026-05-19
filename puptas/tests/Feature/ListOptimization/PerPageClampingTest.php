<?php

use App\Models\TestPasser;
use App\Models\PasserStatus;
use App\Models\User;

/**
 * Property-based test: Per-page clamping
 *
 * Feature: list-optimization
 * Property 5: Per-page clamping
 *
 * For any integer per_page input value, the effective per_page used by the system SHALL equal
 * max(1, min(100, input_value)). For any non-integer or non-numeric per_page input, the
 * effective per_page SHALL be 15.
 *
 * **Validates: Requirements 1.5, 1.7**
 */

beforeEach(function () {
    // Ensure passer_statuses exist for factory
    PasserStatus::firstOrCreate(['id' => 1], ['status' => 'qualified']);
    PasserStatus::firstOrCreate(['id' => 2], ['status' => 'waitlisted']);
    PasserStatus::firstOrCreate(['id' => 3], ['status' => 'unqualified']);

    // Create an admin user for authentication
    $this->adminUser = User::factory()->create(['role_id' => 2]);

    // Create enough TestPasser records to test pagination (50+ records)
    TestPasser::factory()->count(55)->create([
        'school_year' => '2024-2025',
        'batch_number' => '1',
    ]);
});

// Generate random integer per_page values including negatives, zero, and large numbers
$numericPerPageCases = [];
$seed = 42;

for ($i = 0; $i < max(10, propertyTestIterations()); $i++) {
    $seed = ($seed * 1103515245 + 12345) & 0x7fffffff;

    // Generate values from different ranges: negatives, zero, normal, large
    $range = $seed % 5;
    switch ($range) {
        case 0: // Negative values
            $value = -($seed % 1000) - 1;
            break;
        case 1: // Zero
            $value = 0;
            break;
        case 2: // Normal range (1-100)
            $value = ($seed % 100) + 1;
            break;
        case 3: // Large numbers (>100)
            $value = ($seed % 10000) + 101;
            break;
        case 4: // Very large or very negative
            $value = ($seed % 2 === 0) ? $seed : -$seed;
            break;
    }

    $numericPerPageCases[] = [$value];
}

it(
    'Property 5: Per-page clamping - integer per_page values are clamped to max(1, min(100, input_value))',
    function (int $inputPerPage) {
        $expectedPerPage = max(1, min(100, $inputPerPage));

        // Make authenticated GET request with the per_page value
        $response = $this->actingAs($this->adminUser)
            ->get('/test-passers?' . http_build_query([
                'school_year' => '2024-2025',
                'batch_number' => '1',
                'per_page' => $inputPerPage,
            ]));

        $response->assertStatus(200);

        // Extract the paginated passers from the Inertia response
        $page = $response->viewData('page');
        $actualPerPage = $page['props']['passers']['per_page'] ?? null;

        expect($actualPerPage)->toBe(
            $expectedPerPage,
            "For input per_page={$inputPerPage}, expected effective per_page={$expectedPerPage}, got {$actualPerPage}"
        );
    }
)->with($numericPerPageCases);

// Generate non-numeric per_page values
$nonNumericPerPageCases = [
    ['abc'],
    [''],
    ['hello world'],
    ['twelve'],
    ['1.5.3'],
    ['null'],
    ['true'],
    ['false'],
    ['NaN'],
    ['undefined'],
    ['%20'],
    ['<script>'],
];

it(
    'Property 5: Per-page clamping - non-numeric per_page values default to 15',
    function (string $inputPerPage) {
        // Make authenticated GET request with the non-numeric per_page value
        $response = $this->actingAs($this->adminUser)
            ->get('/test-passers?' . http_build_query([
                'school_year' => '2024-2025',
                'batch_number' => '1',
                'per_page' => $inputPerPage,
            ]));

        $response->assertStatus(200);

        // Extract the paginated passers from the Inertia response
        $page = $response->viewData('page');
        $actualPerPage = $page['props']['passers']['per_page'] ?? null;

        expect($actualPerPage)->toBe(
            15,
            "For non-numeric input per_page='{$inputPerPage}', expected effective per_page=15, got {$actualPerPage}"
        );
    }
)->with($nonNumericPerPageCases);
