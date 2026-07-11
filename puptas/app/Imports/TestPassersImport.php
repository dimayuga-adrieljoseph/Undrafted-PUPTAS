<?php

namespace App\Imports;

use App\Models\TestPasser;
use App\Models\User;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class TestPassersImport implements ToModel, WithHeadingRow
{
    protected ?string $batch;
    protected string $schoolYear;
    protected int $passerStatusId;
    protected int $importedCount = 0;
    protected int $skippedCount = 0;
    protected array $skippedReasons = [];

    public function __construct(
        ?string $batch = null,
        string $schoolYear = '',
        int $passerStatusId = 1
    ) {
        $this->batch = $batch;
        $this->schoolYear = $schoolYear;
        $this->passerStatusId = $passerStatusId;
    }

    public function model(array $row): ?TestPasser
    {
        // Trim and check firstname
        $firstName = isset($row['firstname']) ? trim((string)$row['firstname']) : '';

        if ($firstName === '') {
            $this->skippedCount++;
            $this->skippedReasons[] = 'Missing first name';
            return null;
        }

        // Score resolution strictly based on "pupcet_score" column
        $pupcetScore = null;
        if (isset($row['pupcet_score']) && trim((string)$row['pupcet_score']) !== '') {
            $rawScore = $row['pupcet_score'];
            if (is_numeric($rawScore)) {
                $floatScore = (float)$rawScore;
                if ($floatScore >= 0.00 && $floatScore <= 9999.99) {
                    $pupcetScore = round($floatScore, 2);
                }
            }
        }

        $email = isset($row['email']) ? trim((string)$row['email']) : '';
        $email = $email !== '' ? $email : null;

        $referenceNumber = isset($row['reference_number']) ? trim((string)$row['reference_number']) : '';
        $referenceNumber = $referenceNumber !== '' ? $referenceNumber : null;

        $schoolName = null;
        if (isset($row['school_name']) && trim((string)$row['school_name']) !== '') {
            $schoolName = trim((string)$row['school_name']);
        } elseif (isset($row['school']) && trim((string)$row['school']) !== '') {
            $schoolName = trim((string)$row['school']);
        }

        // ── Centralized duplicate check ──────────────────────────────
        // Both `email` and `reference_number` have global UNIQUE
        // constraints at the database level.  We must check BOTH before
        // inserting, regardless of whether the row has an email or not.
        if ($email && TestPasser::where('email', $email)->exists()) {
            $this->skippedCount++;
            $this->skippedReasons[] = "Duplicate email: {$email}";
            return null;
        }

        if ($referenceNumber && TestPasser::where('reference_number', $referenceNumber)->exists()) {
            $this->skippedCount++;
            $this->skippedReasons[] = "Duplicate reference_number: {$referenceNumber}";
            return null;
        }

        // ── Build payload ────────────────────────────────────────────
        $userId = null;
        $status = 'pending';

        // Check if user exists with matching email
        if ($email) {
            $user = User::where('email', $email)->first();
            if ($user) {
                $userId = $user->id;
                $status = 'registered';

                // Update applicantProfile's student_number if profile
                // and reference number are present
                if ($user->applicantProfile && $referenceNumber) {
                    $user->applicantProfile->update(['student_number' => $referenceNumber]);
                }
            }
        }

        $this->importedCount++;

        return TestPasser::create([
            'surname'            => isset($row['surname']) ? trim((string)$row['surname']) : null,
            'first_name'         => $firstName,
            'middle_name'        => isset($row['middle_name']) ? trim((string)$row['middle_name']) : null,
            'strand'             => isset($row['strand']) ? trim((string)$row['strand']) : null,
            'shs_school'         => $schoolName,
            'email'              => $email,
            'reference_number'   => $referenceNumber,
            'pupcet_total_score' => $pupcetScore,
            'batch_number'       => $this->batch,
            'school_year'        => $this->schoolYear,
            'user_id'            => $userId,
            'status'             => $status,
            'passer_status_id'   => $this->passerStatusId,
        ]);
    }

    public function getImportedCount(): int
    {
        return $this->importedCount;
    }

    public function getSkippedCount(): int
    {
        return $this->skippedCount;
    }

    public function getSkippedReasons(): array
    {
        return $this->skippedReasons;
    }
}