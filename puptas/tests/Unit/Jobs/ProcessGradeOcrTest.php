<?php

namespace Tests\Unit\Jobs;

use App\Jobs\ProcessGradeOcr;
use App\Models\User;
use App\Models\UserFile;
use App\Services\DoclingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

/**
 * Unit tests for ProcessGradeOcr::handle()
 *
 * Validates: Requirements 1.1, 1.2, 1.3, 1.4, 2.1, 2.2, 2.3, 2.4
 *
 * These tests verify the skip-reprocessing guard and the existing job behaviour
 * for unprocessed files. DoclingService is mocked/spied to assert call counts.
 */
class ProcessGradeOcrTest extends TestCase
{
    use RefreshDatabase;

    private static int $typeCounter = 0;

    /**
     * Create a UserFile record with the given attributes.
     * Uses a unique type per call to avoid the unique(user_id, type) constraint.
     */
    private function makeUserFile(array $attributes = []): UserFile
    {
        self::$typeCounter++;

        $user = User::factory()->create();

        return UserFile::create(array_merge([
            'user_id'       => $user->id,
            'type'          => 'file10_front_' . self::$typeCounter,
            'file_path'     => 'grades/test-file-' . self::$typeCounter . '.webp',
            'original_name' => 'test-file.webp',
            'status'        => 'pending',
            'docling_json'  => null,
        ], $attributes));
    }

    /**
     * Run the job's handle() method with the given DoclingService mock.
     */
    private function runJob(int $userFileId, DoclingService $doclingService): void
    {
        $job = new ProcessGradeOcr($userFileId);
        $job->handle($doclingService);
    }

    // -------------------------------------------------------------------------
    // Requirement 1.2 — docling_json = null → convertToJson() is called once
    // -------------------------------------------------------------------------

    /**
     * @test
     * **Validates: Requirements 1.2, 2.1**
     */
    public function docling_json_null_calls_convert_to_json_once(): void
    {
        $userFile = $this->makeUserFile(['docling_json' => null]);

        Storage::fake('public');
        Storage::disk('public')->put($userFile->file_path, 'fake-webp-bytes');

        $doclingService = Mockery::mock(DoclingService::class);
        $doclingService->shouldReceive('convertToJson')
            ->once()
            ->andReturn(['some' => 'data']);

        $this->runJob($userFile->id, $doclingService);
    }

    // -------------------------------------------------------------------------
    // Requirement 1.3 — docling_json = [] → convertToJson() is called once
    // -------------------------------------------------------------------------

    /**
     * @test
     * **Validates: Requirements 1.3, 2.1**
     */
    public function docling_json_empty_array_calls_convert_to_json_once(): void
    {
        $userFile = $this->makeUserFile(['docling_json' => []]);

        Storage::fake('public');
        Storage::disk('public')->put($userFile->file_path, 'fake-webp-bytes');

        $doclingService = Mockery::mock(DoclingService::class);
        $doclingService->shouldReceive('convertToJson')
            ->once()
            ->andReturn(['some' => 'data']);

        $this->runJob($userFile->id, $doclingService);
    }

    // -------------------------------------------------------------------------
    // Requirement 1.1, 1.4 — docling_json = non-empty → skip, no call, no mutation
    // -------------------------------------------------------------------------

    /**
     * @test
     * **Validates: Requirements 1.1, 1.4**
     */
    public function docling_json_non_empty_does_not_call_convert_to_json(): void
    {
        $existingData = ['texts' => [['text' => 'Subject: Gen Math  Grade: 90']], 'tables' => []];
        $userFile = $this->makeUserFile(['docling_json' => $existingData]);

        $doclingService = Mockery::mock(DoclingService::class);
        $doclingService->shouldNotReceive('convertToJson');

        $this->runJob($userFile->id, $doclingService);
    }

    /**
     * @test
     * **Validates: Requirements 1.1, 1.4**
     */
    public function docling_json_non_empty_value_is_unchanged_after_handle(): void
    {
        $existingData = ['texts' => [['text' => 'Subject: Gen Math  Grade: 90']], 'tables' => []];
        $userFile = $this->makeUserFile(['docling_json' => $existingData]);

        $doclingService = Mockery::mock(DoclingService::class);
        $doclingService->shouldNotReceive('convertToJson');

        $this->runJob($userFile->id, $doclingService);

        $userFile->refresh();
        $this->assertEquals($existingData, $userFile->docling_json);
    }

    // -------------------------------------------------------------------------
    // Requirement 2.3 — UserFile record does not exist → no exception, no DB write
    // -------------------------------------------------------------------------

    /**
     * @test
     * **Validates: Requirements 2.3**
     */
    public function missing_user_file_record_throws_no_exception(): void
    {
        $nonExistentId = 999999;

        $doclingService = Mockery::mock(DoclingService::class);
        $doclingService->shouldNotReceive('convertToJson');

        // Should not throw
        $this->runJob($nonExistentId, $doclingService);

        $this->assertTrue(true); // reached without exception
    }

    /**
     * @test
     * **Validates: Requirements 2.3**
     */
    public function missing_user_file_record_causes_no_db_write(): void
    {
        $nonExistentId = 999999;
        $initialCount = UserFile::count();

        $doclingService = Mockery::mock(DoclingService::class);
        $doclingService->shouldNotReceive('convertToJson');

        $this->runJob($nonExistentId, $doclingService);

        $this->assertEquals($initialCount, UserFile::count());
    }

    // -------------------------------------------------------------------------
    // Requirement 2.4 — File missing from storage → no exception, no API call
    // -------------------------------------------------------------------------

    /**
     * @test
     * **Validates: Requirements 2.4**
     */
    public function missing_file_from_storage_throws_no_exception(): void
    {
        $userFile = $this->makeUserFile(['docling_json' => null]);

        // Fake storage but do NOT put the file — it is missing
        Storage::fake('public');

        $doclingService = Mockery::mock(DoclingService::class);
        $doclingService->shouldNotReceive('convertToJson');

        // Should not throw
        $this->runJob($userFile->id, $doclingService);

        $this->assertTrue(true); // reached without exception
    }

    /**
     * @test
     * **Validates: Requirements 2.4**
     */
    public function missing_file_from_storage_does_not_call_convert_to_json(): void
    {
        $userFile = $this->makeUserFile(['docling_json' => null]);

        Storage::fake('public');

        $doclingService = Mockery::mock(DoclingService::class);
        $doclingService->shouldNotReceive('convertToJson');

        $this->runJob($userFile->id, $doclingService);
    }

    // -------------------------------------------------------------------------
    // Requirement 2.2 — API returns null → docling_json remains null
    // -------------------------------------------------------------------------

    /**
     * @test
     * **Validates: Requirements 2.2**
     */
    public function api_returns_null_leaves_docling_json_as_null(): void
    {
        $userFile = $this->makeUserFile(['docling_json' => null]);

        Storage::fake('public');
        Storage::disk('public')->put($userFile->file_path, 'fake-webp-bytes');

        $doclingService = Mockery::mock(DoclingService::class);
        $doclingService->shouldReceive('convertToJson')
            ->once()
            ->andReturn(null);

        $this->runJob($userFile->id, $doclingService);

        $userFile->refresh();
        $this->assertNull($userFile->docling_json);
    }

    // -------------------------------------------------------------------------
    // Requirement 2.1 — API returns valid array → docling_json is updated
    // -------------------------------------------------------------------------

    /**
     * @test
     * **Validates: Requirements 2.1**
     */
    public function api_returns_valid_array_updates_docling_json(): void
    {
        $userFile = $this->makeUserFile(['docling_json' => null]);

        Storage::fake('public');
        Storage::disk('public')->put($userFile->file_path, 'fake-webp-bytes');

        $apiResult = [
            'texts'  => [['text' => 'Subject: Gen Math  Grade: 90']],
            'tables' => [],
        ];

        $doclingService = Mockery::mock(DoclingService::class);
        $doclingService->shouldReceive('convertToJson')
            ->once()
            ->andReturn($apiResult);

        $this->runJob($userFile->id, $doclingService);

        $userFile->refresh();
        $this->assertEquals($apiResult, $userFile->docling_json);
    }

    // -------------------------------------------------------------------------
    // Property 2: Persistence of API result
    // Feature: docling-skip-reprocessing
    // Property 2: For any non-null API result, handle() persists it to
    //             docling_json when the file was unprocessed
    // Validates: Requirements 2.1
    // -------------------------------------------------------------------------

    /**
     * Property-based test: for any non-empty array returned by DoclingService,
     * handle() persists that exact array to UserFile.docling_json when the file
     * starts with docling_json = null.
     *
     * Runs 100 iterations with randomly generated API result arrays.
     *
     * @test
     * **Validates: Requirements 2.1**
     * Tag: Feature: docling-skip-reprocessing, Property 2: For any non-null API result, handle() persists it to docling_json when the file was unprocessed
     */
    public function property_2_any_non_null_api_result_is_persisted_to_docling_json(): void
    {
        $faker = \Faker\Factory::create();

        $iterations = 100;

        for ($i = 0; $i < $iterations; $i++) {
            // Generate a random non-empty array to simulate a DoclingService result.
            // Arrays vary in structure: some have 'texts', some have 'tables',
            // some have arbitrary keys — all are valid non-empty API results.
            $apiResult = $this->generateRandomNonEmptyArray($faker, $i);

            // Each iteration needs a fresh UserFile with docling_json = null
            // and a fresh fake storage with the file present.
            Storage::fake('public');

            $userFile = $this->makeUserFile(['docling_json' => null]);
            Storage::disk('public')->put($userFile->file_path, 'fake-webp-bytes-' . $i);

            $doclingService = Mockery::mock(DoclingService::class);
            $doclingService->shouldReceive('convertToJson')
                ->once()
                ->andReturn($apiResult);

            $this->runJob($userFile->id, $doclingService);

            $userFile->refresh();

            $this->assertEquals(
                $apiResult,
                $userFile->docling_json,
                sprintf(
                    'Iteration %d failed: expected docling_json to equal the API result, but got a different value. API result was: %s',
                    $i + 1,
                    json_encode($apiResult)
                )
            );

            Mockery::close();
        }
    }

    // =========================================================================
    // Property 1: Skip invariant
    // Feature: docling-skip-reprocessing
    // Property 1: For any UserFile with non-empty docling_json, handle() does
    //             not call convertToJson() and does not mutate the value
    // Validates: Requirements 1.1, 1.4
    // =========================================================================

    /**
     * Property-based test: for any UserFile whose docling_json is a non-null,
     * non-empty array, ProcessGradeOcr::handle() SHALL NOT call
     * DoclingService::convertToJson() and SHALL NOT modify the docling_json value.
     *
     * Runs a minimum of 100 iterations over randomly generated non-empty arrays.
     *
     * @test
     * @group docling-skip-reprocessing
     * **Validates: Requirements 1.1, 1.4**
     * Tag: Feature: docling-skip-reprocessing, Property 1: For any UserFile with non-empty docling_json, handle() does not call convertToJson() and does not mutate the value
     */
    public function property_1_skip_invariant_for_any_non_empty_docling_json(): void
    {
        $faker = \Faker\Factory::create();

        // Respect the env override but enforce a minimum of 100 iterations.
        $iterations = max(100, (int) env('PROPERTY_TEST_ITERATIONS', 100));

        for ($i = 0; $i < $iterations; $i++) {
            // --- Generate a random non-empty array ----------------------------
            $doclingJson = $this->generateRandomNonEmptyDoclingJson($faker, $i);

            // --- Set up the UserFile with the generated value -----------------
            $userFile = $this->makeUserFile(['docling_json' => $doclingJson]);

            // --- Mock: convertToJson must NEVER be called ---------------------
            $doclingService = Mockery::mock(DoclingService::class);
            $doclingService->shouldNotReceive('convertToJson');

            // --- Execute the job ----------------------------------------------
            $this->runJob($userFile->id, $doclingService);

            // --- Assert: docling_json is unchanged ----------------------------
            $userFile->refresh();
            $this->assertEquals(
                $doclingJson,
                $userFile->docling_json,
                sprintf(
                    'Property 1 failed at iteration %d: docling_json was mutated. ' .
                    'Input: %s, Got: %s',
                    $i + 1,
                    json_encode($doclingJson),
                    json_encode($userFile->docling_json)
                )
            );

            // Clean up Mockery expectations between iterations
            Mockery::close();
        }
    }

    /**
     * Generate a random non-empty array that could plausibly be a docling_json value.
     * Varies structure across iterations to exercise the skip invariant broadly.
     */
    private function generateRandomNonEmptyDoclingJson(\Faker\Generator $faker, int $seed): array
    {
        // Cycle through several structural shapes to maximise variety
        $shape = $seed % 6;

        return match ($shape) {
            // Shape 0: typical docling output with texts and tables
            0 => [
                'texts'  => array_map(
                    fn () => ['text' => $faker->sentence()],
                    range(1, $faker->numberBetween(1, 5))
                ),
                'tables' => [],
            ],

            // Shape 1: deeply nested structure
            1 => [
                'pages' => [
                    [
                        'page_no' => $faker->numberBetween(1, 10),
                        'texts'   => [['text' => $faker->paragraph()]],
                    ],
                ],
            ],

            // Shape 2: flat key-value pairs (arbitrary JSON object)
            2 => (function () use ($faker): array {
                $result = [];
                $numKeys = $faker->numberBetween(1, 8);
                for ($k = 0; $k < $numKeys; $k++) {
                    $result['key_' . $k] = $faker->word();
                }
                return $result;
            })(),

            // Shape 3: array of grade-like records
            3 => array_map(
                fn () => [
                    'subject' => $faker->words($faker->numberBetween(2, 5), true),
                    'grade'   => $faker->numberBetween(60, 100),
                    'units'   => $faker->numberBetween(1, 6),
                ],
                range(1, $faker->numberBetween(1, 10))
            ),

            // Shape 4: single-element array (minimal non-empty case)
            4 => ['value' => $faker->word()],

            // Shape 5: mixed nested structure with metadata
            default => [
                'id'      => $faker->uuid(),
                'content' => $faker->text(200),
                'meta'    => ['source' => $faker->url(), 'page' => $faker->numberBetween(1, 50)],
            ],
        };
    }

    /**
     * Generate a random non-empty array to simulate a DoclingService API result.
     * Varies structure across iterations to exercise the persistence property
     * across a wide range of shapes.
     */
    private function generateRandomNonEmptyArray(\Faker\Generator $faker, int $seed): array
    {
        // Cycle through several structural variants so the 100 iterations
        // cover different shapes of API response.
        $variant = $seed % 5;

        switch ($variant) {
            case 0:
                // Typical docling response with texts and tables
                return [
                    'texts'  => array_map(
                        fn () => ['text' => $faker->sentence(), 'page' => $faker->numberBetween(1, 10)],
                        range(1, $faker->numberBetween(1, 5))
                    ),
                    'tables' => [],
                ];

            case 1:
                // Response with tables only
                return [
                    'texts'  => [],
                    'tables' => array_map(
                        fn () => [
                            'rows' => $faker->numberBetween(1, 10),
                            'cols' => $faker->numberBetween(1, 5),
                            'data' => $faker->words($faker->numberBetween(2, 8)),
                        ],
                        range(1, $faker->numberBetween(1, 3))
                    ),
                ];

            case 2:
                // Flat key-value array (arbitrary API shape)
                $result = [];
                $numKeys = $faker->numberBetween(1, 8);
                for ($k = 0; $k < $numKeys; $k++) {
                    $result[$faker->unique()->word()] = $faker->sentence();
                }
                $faker->unique(true); // reset unique generator
                return $result;

            case 3:
                // Nested array with grade-like data
                return [
                    'grades' => array_map(
                        fn () => [
                            'subject' => $faker->words(3, true),
                            'grade'   => $faker->numberBetween(60, 100),
                            'units'   => $faker->numberBetween(1, 6),
                        ],
                        range(1, $faker->numberBetween(1, 10))
                    ),
                    'metadata' => ['page_count' => $faker->numberBetween(1, 5)],
                ];

            default:
                // Single-element array — minimal non-empty result
                return [['value' => $faker->uuid()]];
        }
    }
}
