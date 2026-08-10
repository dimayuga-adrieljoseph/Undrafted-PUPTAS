<?php

use App\Models\TestPasser;
use App\Models\PasserStatus;
use App\Http\Controllers\TestPasserController;
use Illuminate\Http\Request;

/**
 * Unit tests for default school_year/batch_number selection logic.
 *
 * Feature: list-optimization
 * **Validates: Requirements 3.5, 5.4**
 */

beforeEach(function () {
    // Ensure passer_statuses exist for factory
    PasserStatus::firstOrCreate(['id' => 1], ['status' => 'qualified']);
    PasserStatus::firstOrCreate(['id' => 2], ['status' => 'waitlisted']);
    PasserStatus::firstOrCreate(['id' => 3], ['status' => 'unqualified']);
});

it('returns null school_year when no school_year filter is provided and database is empty', function () {
    // No records in the database
    $controller = app(TestPasserController::class);
    $method = new ReflectionMethod($controller, 'getDefaultFilters');
    $method->setAccessible(true);

    $request = Request::create('/test-passers', 'GET', []);
    $result = $method->invoke($controller, $request);

    expect($result['school_year'])->toBeNull();
});

it('uses the provided school_year when it is in the request', function () {
    TestPasser::factory()->create(['school_year' => '2022-2023', 'batch_number' => '1']);
    TestPasser::factory()->create(['school_year' => '2024-2025', 'batch_number' => '1']);

    $controller = app(TestPasserController::class);
    $method = new ReflectionMethod($controller, 'getDefaultFilters');
    $method->setAccessible(true);

    $request = Request::create('/test-passers', 'GET', ['school_year' => '2022-2023']);
    $result = $method->invoke($controller, $request);

    expect($result['school_year'])->toBe('2022-2023');
});

it('defaults batch_number to "all" when not provided', function () {
    TestPasser::factory()->create(['school_year' => '2024-2025', 'batch_number' => '3']);
    TestPasser::factory()->create(['school_year' => '2024-2025', 'batch_number' => '1']);
    TestPasser::factory()->create(['school_year' => '2024-2025', 'batch_number' => '2']);

    $controller = app(TestPasserController::class);
    $method = new ReflectionMethod($controller, 'getDefaultFilters');
    $method->setAccessible(true);

    $request = Request::create('/test-passers', 'GET', []);
    $result = $method->invoke($controller, $request);

    expect($result['school_year'])->toBe('2024-2025');
    expect($result['batch_number'])->toBeNull(); // defaults to "all" in index(), null in getDefaultFilters
});

it('uses the provided batch_number when it is in the request', function () {
    TestPasser::factory()->create(['school_year' => '2024-2025', 'batch_number' => '1']);
    TestPasser::factory()->create(['school_year' => '2024-2025', 'batch_number' => '2']);

    $controller = app(TestPasserController::class);
    $method = new ReflectionMethod($controller, 'getDefaultFilters');
    $method->setAccessible(true);

    $request = Request::create('/test-passers', 'GET', ['batch_number' => '2']);
    $result = $method->invoke($controller, $request);

    expect($result['batch_number'])->toBe('2');
});

it('returns null defaults when the database is empty', function () {
    // No records in the database
    $controller = app(TestPasserController::class);
    $method = new ReflectionMethod($controller, 'getDefaultFilters');
    $method->setAccessible(true);

    $request = Request::create('/test-passers', 'GET', []);
    $result = $method->invoke($controller, $request);

    expect($result['school_year'])->toBeNull();
    expect($result['batch_number'])->toBeNull();
});

it('returns null batch_number when school_year has no batch records', function () {
    // Create a record with null batch_number
    TestPasser::factory()->create(['school_year' => '2024-2025', 'batch_number' => null]);

    $controller = app(TestPasserController::class);
    $method = new ReflectionMethod($controller, 'getDefaultFilters');
    $method->setAccessible(true);

    $request = Request::create('/test-passers', 'GET', []);
    $result = $method->invoke($controller, $request);

    expect($result['school_year'])->toBe('2024-2025');
    expect($result['batch_number'])->toBeNull();
});
