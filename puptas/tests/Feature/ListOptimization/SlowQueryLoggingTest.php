<?php

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;

/**
 * Unit test for slow query logging via DB::listen().
 *
 * Feature: list-optimization
 * **Validates: Requirements 6.2**
 */

it('logs a warning when a query exceeds 500ms', function () {
    Log::shouldReceive('warning')
        ->once()
        ->withArgs(function (string $message, array $context) {
            return $message === 'Slow query detected'
                && array_key_exists('sql', $context)
                && array_key_exists('bindings', $context)
                && array_key_exists('time_ms', $context);
        });

    // Simulate a slow query by dispatching a QueryExecuted event with time > 500ms
    $connection = DB::connection();

    $event = new QueryExecuted(
        'SELECT * FROM test_passers WHERE school_year = ?',
        ['2024-2025'],
        600.0, // 600ms - exceeds the 500ms threshold
        $connection
    );

    // Dispatch the event to trigger the DB::listen callback
    event($event);
});

it('does not log when a query is under 500ms', function () {
    Log::shouldReceive('warning')->never();

    $connection = DB::connection();

    $event = new QueryExecuted(
        'SELECT * FROM test_passers WHERE school_year = ?',
        ['2024-2025'],
        200.0, // 200ms - under the 500ms threshold
        $connection
    );

    event($event);
});

it('logs the correct SQL text in the warning', function () {
    $expectedSql = 'SELECT * FROM test_passers WHERE strand = ?';

    Log::shouldReceive('warning')
        ->once()
        ->withArgs(function (string $message, array $context) use ($expectedSql) {
            return $message === 'Slow query detected'
                && $context['sql'] === $expectedSql;
        });

    $connection = DB::connection();

    $event = new QueryExecuted(
        $expectedSql,
        ['STEM'],
        750.0,
        $connection
    );

    event($event);
});

it('logs the correct bindings in the warning', function () {
    $expectedBindings = ['2024-2025', 'Batch 1'];

    Log::shouldReceive('warning')
        ->once()
        ->withArgs(function (string $message, array $context) use ($expectedBindings) {
            return $message === 'Slow query detected'
                && $context['bindings'] === $expectedBindings;
        });

    $connection = DB::connection();

    $event = new QueryExecuted(
        'SELECT * FROM test_passers WHERE school_year = ? AND batch_number = ?',
        $expectedBindings,
        501.0, // Just over the threshold
        $connection
    );

    event($event);
});

it('logs the correct time_ms in the warning', function () {
    $expectedTime = 1234.5;

    Log::shouldReceive('warning')
        ->once()
        ->withArgs(function (string $message, array $context) use ($expectedTime) {
            return $message === 'Slow query detected'
                && $context['time_ms'] === $expectedTime;
        });

    $connection = DB::connection();

    $event = new QueryExecuted(
        'SELECT 1',
        [],
        $expectedTime,
        $connection
    );

    event($event);
});

it('does not log at exactly 500ms (boundary condition)', function () {
    Log::shouldReceive('warning')->never();

    $connection = DB::connection();

    $event = new QueryExecuted(
        'SELECT * FROM test_passers',
        [],
        500.0, // Exactly 500ms - the condition is > 500, not >=
        $connection
    );

    event($event);
});
