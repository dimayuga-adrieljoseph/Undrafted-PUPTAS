<?php

use App\Models\TestPasser;
use App\Models\PasserStatus;
use App\Models\User;

/**
 * Property-based test for pagination metadata consistency.
 *
 * Feature: list-optimization
 * Property 3: Pagination metadata consistency
 * **Validates: Requirements 1.3**
 *
 * For any paginated response, asserts:
 * - last_page == ceil(total / per_page)
 * - current_page >= 1 && current_page <= last_page (when total > 0)
 * - total equals actual count of records matching applied filters
 */

beforeEach(function () {
    // Ensure passer_statuses exist for factory
    PasserStatus::firstOrCreate(['id' => 1], ['status' => 'qualified']);
    PasserStatus::firstOrCreate(['id' => 2], ['status' => 'waitlisted']);
    PasserStatus::firstOrCreate(['id' => 3], ['status' => 'unqualified']);
});

it('Property 3: Pagination metadata consistency - last_page, current_page, and total are consistent', function () {
    $iterations = max(10, propertyTestIterations());
    $user = User::factory()->create(['role_id' => 2]); // Admin role for EnsureAdminOrRegistrar middleware

    for ($i = 0; $i < $iterations; $i++) {
        // Clean up records from previous iteration
        TestPasser::query()->delete();

        // Generate a random number of records (1 to 50)
        $recordCount = rand(1, 50);
        $schoolYear = fake()->randomElement(['2023-2024', '2024-2025', '2025-2026']);
        $batchNumber = fake()->randomElement(['1', '2', '3']);

        TestPasser::factory()->count($recordCount)->create([
            'school_year' => $schoolYear,
            'batch_number' => $batchNumber,
        ]);

        // Generate random per_page (valid range 1-100)
        $perPage = rand(1, 100);

        // Calculate expected last_page
        $expectedLastPage = (int) ceil($recordCount / $perPage);

        // Pick a random valid page
        $page = rand(1, $expectedLastPage);

        // Make request with explicit filters to match all created records
        $response = $this->actingAs($user)->get('/test-passers?' . http_build_query([
            'school_year' => $schoolYear,
            'batch_number' => $batchNumber,
            'per_page' => $perPage,
            'page' => $page,
        ]));

        $response->assertStatus(200);

        // Extract pagination data from Inertia response
        $passers = $response->original->getData()['page']['props']['passers'];

        $total = $passers['total'];
        $currentPage = $passers['current_page'];
        $lastPage = $passers['last_page'];
        $effectivePerPage = $passers['per_page'];

        // Assert: last_page == ceil(total / per_page)
        $expectedLastPageFromResponse = (int) ceil($total / $effectivePerPage);
        expect($lastPage)->toBe(
            $expectedLastPageFromResponse,
            "Iteration {$i}: last_page ({$lastPage}) should equal ceil(total ({$total}) / per_page ({$effectivePerPage})) = {$expectedLastPageFromResponse}"
        );

        // Assert: current_page >= 1 && current_page <= last_page (when total > 0)
        if ($total > 0) {
            expect($currentPage)->toBeGreaterThanOrEqual(1,
                "Iteration {$i}: current_page ({$currentPage}) should be >= 1"
            );
            expect($currentPage)->toBeLessThanOrEqual($lastPage,
                "Iteration {$i}: current_page ({$currentPage}) should be <= last_page ({$lastPage})"
            );
        }

        // Assert: total equals actual DB count matching the applied filters
        $actualDbCount = TestPasser::where('school_year', $schoolYear)
            ->where('batch_number', $batchNumber)
            ->count();

        expect($total)->toBe($actualDbCount,
            "Iteration {$i}: total ({$total}) should equal actual DB count ({$actualDbCount}) for school_year={$schoolYear}, batch_number={$batchNumber}"
        );
    }
})->group('list-optimization', 'property-test');
