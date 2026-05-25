<?php

/**
 * Property 4: Sort invariant
 *
 * For any result set returned by the endpoint, the records SHALL be ordered
 * such that for every consecutive pair of records (r[i], r[i+1]),
 * r[i].pupcet_total_score >= r[i+1].pupcet_total_score.
 *
 * Feature: list-optimization
 * Property 4: Sort invariant
 * **Validates: Requirements 3.4, 4.5**
 */

use App\Models\TestPasser;
use App\Models\PasserStatus;
use App\Models\User;

beforeEach(function () {
    // Ensure passer_statuses exist for factory
    PasserStatus::firstOrCreate(['id' => 1], ['status' => 'qualified']);
    PasserStatus::firstOrCreate(['id' => 2], ['status' => 'waitlisted']);
    PasserStatus::firstOrCreate(['id' => 3], ['status' => 'unqualified']);
});

it('Property 4: Sort invariant - records are ordered by pupcet_total_score descending', function () {
    $iterations = propertyTestIterations();
    $seed = 42;

    for ($i = 0; $i < $iterations; $i++) {
        // Generate a random number of records (between 5 and 25) with varied scores
        $seed = ($seed * 1103515245 + 12345) & 0x7fffffff;
        $recordCount = ($seed % 21) + 5; // 5 to 25 records

        // Clean up previous iteration data
        TestPasser::query()->delete();

        $schoolYear = '2024-2025';
        $batchNumber = '1';

        // Create records with random pupcet_total_score values
        for ($j = 0; $j < $recordCount; $j++) {
            $seed = ($seed * 1103515245 + 12345) & 0x7fffffff;
            $score = round(($seed % 4001) / 100 + 60, 2); // Scores between 60.00 and 100.00

            TestPasser::factory()->create([
                'school_year' => $schoolYear,
                'batch_number' => $batchNumber,
                'pupcet_total_score' => $score,
            ]);
        }

        // Make authenticated request to the endpoint
        $user = User::factory()->create(['role_id' => 2]);

        $response = $this->actingAs($user)
            ->get('/test-passers?school_year=' . $schoolYear . '&batch_number=' . $batchNumber);

        $response->assertStatus(200);

        // Extract records from the Inertia paginated response
        $page = $response->original->getData()['page'];
        $passers = $page['props']['passers']['data'] ?? [];

        // Assert sort invariant: for every consecutive pair, r[i].pupcet_total_score >= r[i+1].pupcet_total_score
        for ($k = 0; $k < count($passers) - 1; $k++) {
            $currentScore = (float) $passers[$k]['pupcet_total_score'];
            $nextScore = (float) $passers[$k + 1]['pupcet_total_score'];

            expect($currentScore)->toBeGreaterThanOrEqual(
                $nextScore,
                "Iteration {$i}: Record at index {$k} (score={$currentScore}) should have score >= record at index " . ($k + 1) . " (score={$nextScore})"
            );
        }

        // Clean up user for next iteration
        $user->delete();
    }
});
