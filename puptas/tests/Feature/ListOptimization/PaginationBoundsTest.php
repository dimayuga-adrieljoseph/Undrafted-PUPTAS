<?php

use App\Models\TestPasser;
use App\Models\PasserStatus;
use App\Models\User;

/**
 * Property-based tests for pagination bounds.
 *
 * Feature: list-optimization
 * Property 2: Pagination bounds
 * **Validates: Requirements 1.1, 1.2, 1.5**
 *
 * For any valid per_page value (after clamping to [1, 100]) and any dataset,
 * the number of records returned in a single page SHALL be less than or equal
 * to the effective per_page value, and the page offset SHALL equal
 * (current_page - 1) * per_page.
 */

beforeEach(function () {
    // Ensure passer_statuses exist for factory
    PasserStatus::firstOrCreate(['id' => 1], ['status' => 'qualified']);
    PasserStatus::firstOrCreate(['id' => 2], ['status' => 'waitlisted']);
    PasserStatus::firstOrCreate(['id' => 3], ['status' => 'unqualified']);

    // Create an admin user for authentication (role_id = 2)
    $this->adminUser = User::factory()->create(['role_id' => 2]);
});

/**
 * Generate random test cases for pagination bounds property testing.
 * Each case: [recordCount, requestedPage, requestedPerPage]
 */
$paginationBoundsCases = [];
for ($i = 0; $i < max(10, propertyTestIterations()); $i++) {
    $recordCount = rand(5, 50);
    $requestedPage = rand(1, 10);
    $requestedPerPage = rand(1, 150); // includes values outside [1, 100] to test clamping
    $paginationBoundsCases[] = [$recordCount, $requestedPage, $requestedPerPage];
}

it(
    'Property 2: Pagination bounds - returned record count is ≤ effective per_page and offset is correct',
    function (int $recordCount, int $requestedPage, int $requestedPerPage) {
        // Arrange: Create random number of TestPasser records
        TestPasser::factory()->count($recordCount)->create();

        // Calculate effective per_page (clamped to [1, 100])
        $effectivePerPage = max(1, min(100, $requestedPerPage));

        // Calculate total pages
        $lastPage = (int) ceil($recordCount / $effectivePerPage);

        // Calculate effective page (clamped to valid range)
        $effectivePage = max(1, min($requestedPage, $lastPage));

        // Act: Make GET request with random page and per_page values
        $response = $this->actingAs($this->adminUser)
            ->get('/test-passers?' . http_build_query([
                'page' => $requestedPage,
                'per_page' => $requestedPerPage,
            ]));

        $response->assertStatus(200);

        // Extract the paginator from the Inertia response props
        $page = $response->viewData('page');
        $props = $page['props'];
        $passers = $props['passers'];

        // Get pagination data
        $returnedCount = count($passers['data']);
        $currentPage = $passers['current_page'];
        $perPage = $passers['per_page'];
        $from = $passers['from'];

        // Assert 1: Returned record count ≤ effective per_page
        expect($returnedCount)->toBeLessThanOrEqual($effectivePerPage,
            "Returned {$returnedCount} records but effective per_page is {$effectivePerPage} " .
            "(requested per_page={$requestedPerPage}, records={$recordCount}, page={$requestedPage})"
        );

        // Assert 2: The per_page in response matches effective per_page
        expect($perPage)->toBe($effectivePerPage,
            "Response per_page is {$perPage} but expected effective per_page {$effectivePerPage} " .
            "(requested per_page={$requestedPerPage})"
        );

        // Assert 3: Page offset is correct: (current_page - 1) * per_page
        // The 'from' field in Laravel pagination = offset + 1 (1-indexed)
        $expectedOffset = ($currentPage - 1) * $effectivePerPage;
        $expectedFrom = $expectedOffset + 1;

        if ($returnedCount > 0) {
            expect($from)->toBe($expectedFrom,
                "Pagination 'from' is {$from} but expected {$expectedFrom} " .
                "(current_page={$currentPage}, per_page={$effectivePerPage}, offset={$expectedOffset})"
            );
        }
    }
)->with($paginationBoundsCases);
