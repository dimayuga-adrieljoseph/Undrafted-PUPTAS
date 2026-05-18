<?php

namespace App\Imports;

use App\Models\TestPasser;
use App\Models\User;
use App\Models\ApplicantProfile;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class TestPassersImport implements ToModel, WithHeadingRow
{
    protected $batch;
    protected $schoolYear;
    protected $passerStatusId;

    public function __construct($batch, $schoolYear, $passerStatusId)
    {
        $this->batch = $batch;
        $this->schoolYear = $schoolYear;
        $this->passerStatusId = $passerStatusId;
    }

    public function model(array $row)
    {
        // Trim and check for required fields, especially first_name
        $firstName = isset($row['firstname']) ? trim($row['firstname']) : null;

        if (empty($firstName)) {
            // Skip this row by returning null (Excel import will ignore)
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
