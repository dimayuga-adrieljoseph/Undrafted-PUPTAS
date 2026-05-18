<?php

namespace App\Imports;

use App\Models\TestPasser;
use App\Models\User;
use App\Models\ApplicantProfile;
use App\Services\ScoreThresholdService;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class TestPassersImport implements ToModel, WithHeadingRow
{
    protected ?string $batch;
    protected string $schoolYear;
    protected ?int $passerStatusId;
    protected string $assignmentMode;
    protected int $importedCount = 0;
    protected int $skippedCount = 0;

    public function __construct(
        ?string $batch = null,
        string $schoolYear = '',
        ?int $passerStatusId = null,
        string $assignmentMode = 'manual'
    ) {
        $this->batch = $batch;
        $this->schoolYear = $schoolYear;
        $this->passerStatusId = $passerStatusId;
        $this->assignmentMode = $assignmentMode;
    }

    public function model(array $row): ?TestPasser
    {
        if ($this->assignmentMode === 'auto') {
            return $this->processAutoMode($row);
        }

        return $this->processManualMode($row);
    }

    /**
     * Process a row in auto mode using ScoreThresholdService to determine batch/status.
     */
    private function processAutoMode(array $row): ?TestPasser
    {
        // Trim and check for required fields, especially first_name
        $firstName = isset($row['firstname']) ? trim($row['firstname']) : null;

        if (empty($firstName)) {
            $this->skippedCount++;
            return null;
        }

        // Resolve PUPCET score - skip row if null
        $pupcetScore = $this->resolvePupcetScore($row);

        if ($pupcetScore === null) {
            $this->skippedCount++;
            return null;
        }

        // Use ScoreThresholdService to determine batch and status
        $service = new ScoreThresholdService();
        $assignment = $service->resolve($pupcetScore);

        $batchNumber = $assignment['batch_number'];
        $passerStatusId = $assignment['passer_status_id'];

        $email           = $row['email'] ?? null;
        $referenceNumber = $row['reference_number'] ?? null;
        $user            = null;

        // If a user with this email already exists, automatically link them and assign student number
        if ($email) {
            $user = User::where('email', $email)->first();
            if ($user && $user->applicantProfile && $referenceNumber) {
                $user->applicantProfile->update(['student_number' => $referenceNumber]);
            }
        }

        // Use request-level schoolYear for all records regardless of Excel column
        $schoolYear = $this->schoolYear;

        $result = null;

        // Skip rows with no email to avoid collisions on null key
        if (empty($email)) {
            $result = TestPasser::create([
                'surname'            => $row['surname'] ?? null,
                'first_name'         => $firstName,
                'middle_name'        => $row['middlename'] ?? null,
                'strand'             => $row['strand'] ?? null,
                'reference_number'   => $referenceNumber,
                'batch_number'       => $batchNumber,
                'school_year'        => $schoolYear,
                'pupcet_total_score' => $pupcetScore,
                'user_id'            => null,
                'status'             => 'pending',
                'passer_status_id'   => $passerStatusId,
            ]);
        } else {
            $result = TestPasser::updateOrCreate(
                ['email' => $email],
                [
                    'surname'            => $row['surname'] ?? null,
                    'first_name'         => $firstName,
                    'middle_name'        => $row['middlename'] ?? null,
                    'strand'             => $row['strand'] ?? null,
                    'email'              => $email,
                    'reference_number'   => $referenceNumber,
                    'batch_number'       => $batchNumber,
                    'school_year'        => $schoolYear,
                    'pupcet_total_score' => $pupcetScore,
                    'user_id'            => $user?->id,
                    'status'             => $user ? 'registered' : 'pending',
                    'passer_status_id'   => $passerStatusId,
                ]
            );
        }

        $this->importedCount++;
        return $result;
    }

    /**
     * Process a row in manual mode (existing behavior refactored into its own method).
     */
    private function processManualMode(array $row): ?TestPasser
    {
        // Trim and check for required fields, especially first_name
        $firstName = isset($row['firstname']) ? trim($row['firstname']) : null;

        if (empty($firstName)) {
            return null;
        }

        $email           = $row['email'] ?? null;
        $referenceNumber = $row['reference_number'] ?? null;
        $user            = null;

        // If a user with this email already exists, automatically link them and assign student number
        if ($email) {
            $user = User::where('email', $email)->first();
            if ($user && $user->applicantProfile && $referenceNumber) {
                $user->applicantProfile->update(['student_number' => $referenceNumber]);
            }
        }

        $pupcetScore = $this->resolvePupcetScore($row);

        // Skip rows with no email to avoid collisions on null key
        if (empty($email)) {
            return TestPasser::create([
                'surname'            => $row['surname'] ?? null,
                'first_name'         => $firstName,
                'middle_name'        => $row['middlename'] ?? null,
                'strand'             => $row['strand'] ?? null,
                'reference_number'   => $referenceNumber,
                'batch_number'       => $this->batch,
                'school_year'        => $this->schoolYear,
                'pupcet_total_score' => $pupcetScore,
                'user_id'            => null,
                'status'             => 'pending',
                'passer_status_id'   => $this->passerStatusId,
            ]);
        }

        return TestPasser::updateOrCreate(
            ['email' => $email],
            [
                'surname'            => $row['surname'] ?? null,
                'first_name'         => $firstName,
                'middle_name'        => $row['middlename'] ?? null,
                'strand'             => $row['strand'] ?? null,
                'email'              => $email,
                'reference_number'   => $referenceNumber,
                'batch_number'       => $this->batch,
                'school_year'        => $this->schoolYear,
                'pupcet_total_score' => $pupcetScore,
                'user_id'            => $user?->id,
                'status'             => $user ? 'registered' : 'pending',
                'passer_status_id'   => $this->passerStatusId,
            ]
        );
    }

    /**
     * Get the count of successfully imported rows.
     */
    public function getImportedCount(): int
    {
        return $this->importedCount;
    }

    /**
     * Get the count of skipped rows.
     */
    public function getSkippedCount(): int
    {
        return $this->skippedCount;
    }

    private function resolvePupcetScore(array $row): ?float
    {
        $value = $row['pupcet_score'] ?? null;

        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        if (!is_numeric($value)) {
            return null;
        }

        $score = round((float) $value, 2);

        if ($score < 0.00 || $score > 9999.99) {
            return null;
        }

        return $score;
    }
}
