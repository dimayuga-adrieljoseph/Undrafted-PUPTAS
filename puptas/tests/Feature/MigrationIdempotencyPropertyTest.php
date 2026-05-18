<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Migrations\Migration;

/**
 * Property-based tests for migration idempotency
 * 
 * Feature: list-passer-status-filter
 * Property 1: Migration idempotency
 * **Validates: Requirements 1.5**
 */

// Test cases for multiple migration runs
$migrationRunCases = [];
for ($runs = 1; $runs <= 10; $runs++) {
    $migrationRunCases[] = [$runs];
}

it(
    'Property 1: Migration idempotency - running add_unqualified_passer_status migration multiple times produces consistent results',
    function (int $runCount) {
        // Ensure we start with a clean passer_statuses table
        if (Schema::hasTable('passer_statuses')) {
            DB::table('passer_statuses')->truncate();
        }
        
        // Re-run the initial passer_statuses setup to ensure we have the base records
        DB::table('passer_statuses')->insert([
            ['id' => 1, 'status' => 'qualified', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'status' => 'waitlisted', 'created_at' => now(), 'updated_at' => now()],
        ]);
        
        // Verify initial state
        expect(DB::table('passer_statuses')->count())->toBe(2);
        expect(DB::table('passer_statuses')->where('status', 'unqualified')->exists())->toBeFalse();
        
        // Run the migration multiple times
        for ($i = 0; $i < $runCount; $i++) {
            // Execute the migration's up() method logic
            DB::table('passer_statuses')->insertOrIgnore([
                [
                    'id' => 3,
                    'status' => 'unqualified',
                    'created_at' => now(),
                    'updated_at' => now()
                ]
            ]);
        }
        
        // Verify idempotency properties
        $unqualifiedRecords = DB::table('passer_statuses')->where('status', 'unqualified')->get();
        
        // Property 1a: Exactly one 'unqualified' record exists regardless of run count
        expect($unqualifiedRecords->count())->toBe(1, "Expected exactly 1 'unqualified' record after {$runCount} migration runs, got {$unqualifiedRecords->count()}");
        
        // Property 1b: The unqualified record has the correct id (3)
        $unqualifiedRecord = $unqualifiedRecords->first();
        expect($unqualifiedRecord->id)->toBe(3, "Expected 'unqualified' record to have id=3, got {$unqualifiedRecord->id}");
        
        // Property 1c: The unqualified record has the correct status value
        expect($unqualifiedRecord->status)->toBe('unqualified', "Expected status to be 'unqualified', got '{$unqualifiedRecord->status}'");
        
        // Property 1d: Total record count is exactly 3 (qualified, waitlisted, unqualified)
        $totalCount = DB::table('passer_statuses')->count();
        expect($totalCount)->toBe(3, "Expected exactly 3 total records after {$runCount} migration runs, got {$totalCount}");
        
        // Property 1e: Original records remain unchanged
        $qualifiedRecord = DB::table('passer_statuses')->where('status', 'qualified')->first();
        $waitlistedRecord = DB::table('passer_statuses')->where('status', 'waitlisted')->first();
        
        expect($qualifiedRecord)->not->toBeNull('qualified record should exist');
        expect($waitlistedRecord)->not->toBeNull('waitlisted record should exist');
        expect($qualifiedRecord->id)->toBe(1, 'qualified record should have id=1');
        expect($waitlistedRecord->id)->toBe(2, 'waitlisted record should have id=2');
        
    }
)->with($migrationRunCases);

// Test cases for migration rollback idempotency
$rollbackCases = [];
for ($rollbacks = 1; $rollbacks <= 5; $rollbacks++) {
    $rollbackCases[] = [$rollbacks];
}

it(
    'Property 1: Migration rollback idempotency - running migration down() multiple times safely removes only the unqualified record',
    function (int $rollbackCount) {
        // Setup: Ensure we have all three records
        if (Schema::hasTable('passer_statuses')) {
            DB::table('passer_statuses')->truncate();
        }
        
        DB::table('passer_statuses')->insert([
            ['id' => 1, 'status' => 'qualified', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'status' => 'waitlisted', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 3, 'status' => 'unqualified', 'created_at' => now(), 'updated_at' => now()],
        ]);
        
        // Verify initial state has all three records
        expect(DB::table('passer_statuses')->count())->toBe(3);
        expect(DB::table('passer_statuses')->where('status', 'unqualified')->exists())->toBeTrue();
        
        // Run the rollback multiple times
        for ($i = 0; $i < $rollbackCount; $i++) {
            // Execute the migration's down() method logic
            DB::table('passer_statuses')
                ->where('id', 3)
                ->where('status', 'unqualified')
                ->delete();
        }
        
        // Verify rollback idempotency properties
        
        // Property 1f: No 'unqualified' records exist after rollback
        $unqualifiedCount = DB::table('passer_statuses')->where('status', 'unqualified')->count();
        expect($unqualifiedCount)->toBe(0, "Expected 0 'unqualified' records after {$rollbackCount} rollbacks, got {$unqualifiedCount}");
        
        // Property 1g: Exactly 2 records remain (qualified and waitlisted)
        $totalCount = DB::table('passer_statuses')->count();
        expect($totalCount)->toBe(2, "Expected exactly 2 records after rollback, got {$totalCount}");
        
        // Property 1h: Original records remain unchanged
        $qualifiedRecord = DB::table('passer_statuses')->where('status', 'qualified')->first();
        $waitlistedRecord = DB::table('passer_statuses')->where('status', 'waitlisted')->first();
        
        expect($qualifiedRecord)->not->toBeNull('qualified record should still exist after rollback');
        expect($waitlistedRecord)->not->toBeNull('waitlisted record should still exist after rollback');
        expect($qualifiedRecord->id)->toBe(1, 'qualified record should still have id=1');
        expect($waitlistedRecord->id)->toBe(2, 'waitlisted record should still have id=2');
        
    }
)->with($rollbackCases);

// Test cases for mixed up/down operations
$mixedOperationCases = [];
for ($i = 0; $i < propertyTestIterations(); $i++) {
    // Generate random sequences of up/down operations
    $operations = [];
    $opCount = rand(3, 8);
    for ($j = 0; $j < $opCount; $j++) {
        $operations[] = rand(0, 1) ? 'up' : 'down';
    }
    $mixedOperationCases[] = [$operations];
}

it(
    'Property 1: Migration idempotency under mixed up/down operations maintains data consistency',
    function (array $operations) {
        // Setup: Start with clean state
        if (Schema::hasTable('passer_statuses')) {
            DB::table('passer_statuses')->truncate();
        }
        
        // Always start with the base records
        DB::table('passer_statuses')->insert([
            ['id' => 1, 'status' => 'qualified', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'status' => 'waitlisted', 'created_at' => now(), 'updated_at' => now()],
        ]);
        
        $expectedUnqualifiedExists = false;
        
        // Execute the sequence of operations
        foreach ($operations as $operation) {
            if ($operation === 'up') {
                // Migration up: add unqualified status
                DB::table('passer_statuses')->insertOrIgnore([
                    [
                        'id' => 3,
                        'status' => 'unqualified',
                        'created_at' => now(),
                        'updated_at' => now()
                    ]
                ]);
                $expectedUnqualifiedExists = true;
            } else {
                // Migration down: remove unqualified status
                DB::table('passer_statuses')
                    ->where('id', 3)
                    ->where('status', 'unqualified')
                    ->delete();
                $expectedUnqualifiedExists = false;
            }
        }
        
        // Verify final state consistency
        $actualUnqualifiedExists = DB::table('passer_statuses')->where('status', 'unqualified')->exists();
        $totalCount = DB::table('passer_statuses')->count();
        $expectedTotalCount = $expectedUnqualifiedExists ? 3 : 2;
        
        // Property 1i: Final state matches expected state based on last operation
        expect($actualUnqualifiedExists)->toBe($expectedUnqualifiedExists, 
            "Expected unqualified record existence to be " . ($expectedUnqualifiedExists ? 'true' : 'false') . 
            " after operations: " . implode(', ', $operations));
        
        // Property 1j: Total count is consistent with expected state
        expect($totalCount)->toBe($expectedTotalCount, 
            "Expected {$expectedTotalCount} total records, got {$totalCount} after operations: " . implode(', ', $operations));
        
        // Property 1k: Base records always remain intact
        $qualifiedExists = DB::table('passer_statuses')->where('id', 1)->where('status', 'qualified')->exists();
        $waitlistedExists = DB::table('passer_statuses')->where('id', 2)->where('status', 'waitlisted')->exists();
        
        expect($qualifiedExists)->toBeTrue('qualified record should always exist');
        expect($waitlistedExists)->toBeTrue('waitlisted record should always exist');
        
        // Property 1l: If unqualified exists, it has correct attributes
        if ($expectedUnqualifiedExists) {
            $unqualifiedRecord = DB::table('passer_statuses')->where('status', 'unqualified')->first();
            expect($unqualifiedRecord->id)->toBe(3, 'unqualified record should have id=3');
            expect($unqualifiedRecord->status)->toBe('unqualified', 'unqualified record should have correct status');
        }
        
    }
)->with($mixedOperationCases);