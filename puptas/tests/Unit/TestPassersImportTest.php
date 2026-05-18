<?php

/**
 * Property-based tests for TestPassersImport.
 *
 * Feature: upload-passer-status
 * Tests use randomized data providers with Faker for property-based testing.
 */

use App\Imports\TestPassersImport;
use App\Models\TestPasser;
use App\Models\PasserStatus;
use App\Models\User;
use App\Models\ApplicantProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

// ---------------------------------------------------------------------------
// Helper Methods for Randomized Data Generation
// ---------------------------------------------------------------------------

/**
 * Generate a valid Excel row with random data for all 7 permitted columns.
 *
 * @return array<string, mixed>
 */
function generateValidRow(): array
{
    $faker = \Faker\Factory::create();

    return [
        'surname'          => $faker->lastName,
        'firstname'        => $faker->firstName,
        'middle_name'      => $faker->optional(0.7)->firstName,
        'strand'           => $faker->randomElement(['STEM', 'ABM', 'HUMSS', 'GAS', 'TVL', 'SPORTS', 'ARTS']),
        'email'            => $faker->unique()->safeEmail,
        'reference_number' => $faker->unique()->numerify('REF-######'),
        'pupcet_score'     => $faker->randomFloat(2, 0, 9999.99),
    ];
}

/**
 * Generate multiple random valid rows.
 *
 * @param int $count Number of rows to generate
 * @return array<int, array<string, mixed>>
 */
function generateRandomRows(int $count): array
{
    $rows = [];
    for ($i = 0; $i < $count; $i++) {
        $rows[] = generateValidRow();
    }
    return $rows;
}

/**
 * Generate a valid row with a specific pupcet_score value.
 *
 * @param mixed $scoreValue The value to use for pupcet_score
 * @return array<string, mixed>
 */
function generateRowWithScore($scoreValue): array
{
    $row = generateValidRow();
    $row['pupcet_score'] = $scoreValue;
    return $row;
}

/**
 * Generate a row with extra legacy columns that should be ignored.
 *
 * @return array<string, mixed>
 */
function generateRowWithExtraColumns(): array
{
    $faker = \Faker\Factory::create();
    $row = generateValidRow();

    // Add legacy/extra columns that should be ignored
    $row['date_of_birth']   = $faker->date();
    $row['address']         = $faker->address;
    $row['school_address']  = $faker->address;
    $row['school']          = $faker->company . ' High School';
    $row['year_graduated']  = $faker->year;
    $row['status']          = $faker->randomElement(['qualified', 'waitlisted', 'unqualified']);

    return $row;
}

/**
 * Run the import with given rows by calling the model() method directly.
 *
 * @param array $rows Array of row data arrays
 * @param int $passerStatusId The passer status ID to apply
 * @param string $batch The batch number
 * @param string $schoolYear The school year
 * @return array<int, \App\Models\TestPasser|null> Array of created/updated models
 */
function runImport(array $rows, int $passerStatusId, string $batch = 'Batch 1', string $schoolYear = '2024-2025'): array
{
    $import = new TestPassersImport($batch, $schoolYear, $passerStatusId);
    $results = [];

    foreach ($rows as $row) {
        $results[] = $import->model($row);
    }

    return $results;
}

/**
 * Seed the passer_statuses table with the 3 required statuses.
 */
function seedPasserStatuses(): void
{
    if (PasserStatus::count() === 0) {
        PasserStatus::insert([
            ['id' => 1, 'status' => 'qualified', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'status' => 'waitlisted', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 3, 'status' => 'unqualified', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }
}

// ---------------------------------------------------------------------------
// Sanity Test: Verify test infrastructure works
// ---------------------------------------------------------------------------

it('can create a TestPasser record using the import helper', function () {
    seedPasserStatuses();

    $row = generateValidRow();
    $results = runImport([$row], 1);

    expect($results)->toHaveCount(1);
    expect($results[0])->toBeInstanceOf(TestPasser::class);
    expect(TestPasser::count())->toBe(1);
});

it('generates valid rows with all required columns', function () {
    $row = generateValidRow();

    expect($row)->toHaveKeys([
        'surname',
        'firstname',
        'middle_name',
        'strand',
        'email',
        'reference_number',
        'pupcet_score',
    ]);
    expect($row['firstname'])->not->toBeEmpty();
    expect($row['email'])->toContain('@');
});

it('generates rows with specific score values', function () {
    $row = generateRowWithScore(85.5);
    expect($row['pupcet_score'])->toBe(85.5);

    $row = generateRowWithScore('invalid');
    expect($row['pupcet_score'])->toBe('invalid');

    $row = generateRowWithScore(null);
    expect($row['pupcet_score'])->toBeNull();
});

it('generates rows with extra legacy columns', function () {
    $row = generateRowWithExtraColumns();

    expect($row)->toHaveKeys([
        'surname', 'firstname', 'middle_name', 'strand',
        'email', 'reference_number', 'pupcet_score',
        'date_of_birth', 'address', 'school_address',
        'school', 'year_graduated', 'status',
    ]);
});

it('runImport helper processes multiple rows', function () {
    seedPasserStatuses();

    $rows = generateRandomRows(3);
    $results = runImport($rows, 2);

    expect($results)->toHaveCount(3);
    expect(TestPasser::count())->toBe(3);
});
