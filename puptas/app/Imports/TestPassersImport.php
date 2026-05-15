<?php

namespace App\Imports;

use App\Models\TestPasser;
use App\Models\User;
use App\Models\ApplicantProfile;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

class TestPassersImport implements ToModel, WithHeadingRow
{
    protected $batch;
    protected $schoolYear;

    public function __construct($batch, $schoolYear)
    {
        $this->batch = $batch;
        $this->schoolYear = $schoolYear;
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

        // Handle Excel numeric date serials and string dates
        $dateOfBirth = null;
        if (!empty($row['date_of_birth'])) {
            $rawDate = $row['date_of_birth'];
            if (is_numeric($rawDate)) {
                // Excel stores dates as numeric serials — convert properly
                try {
                    $dateOfBirth = ExcelDate::excelToDateTimeObject($rawDate)->format('Y-m-d');
                } catch (\Exception $e) {
                    $dateOfBirth = null;
                }
            } else {
                $parsed = strtotime($rawDate);
                $dateOfBirth = $parsed !== false ? date('Y-m-d', $parsed) : null;
            }
        }

        // Resolve PUPCET score from multiple possible column name variants.
        // WithHeadingRow normalises headers: lowercased + spaces→underscores.
        // Accepted column names in the sheet:
        //   "pupcet_total_score", "pupcet total score",
        //   "pupcet_score",       "total_score"
        $pupcetScore = $this->resolveScore($row);
        $admissionType = $this->resolveAdmissionType($row);

        // Skip rows with no email to avoid collisions on null key
        if (empty($email)) {
            return TestPasser::create([
                'surname'            => $row['surname'] ?? null,
                'first_name'         => $firstName,
                'middle_name'        => $row['middlename'] ?? null,
                'date_of_birth'      => $dateOfBirth,
                'address'            => $row['address'] ?? null,
                'school_address'     => $row['school_address'] ?? null,
                'shs_school'         => $row['school'] ?? null,
                'strand'             => $row['strand'] ?? null,
                'year_graduated'     => $row['year_graduated'] ?? null,
                'reference_number'   => $referenceNumber,
                'batch_number'       => $this->batch,
                'school_year'        => $this->schoolYear,
                'pupcet_total_score' => $pupcetScore,
                'user_id'            => null,
                'status'             => 'pending',
                'admission_type'     => $admissionType,
            ]);
        }

        return TestPasser::updateOrCreate(
            ['email' => $email],
            [
                'surname'            => $row['surname'] ?? null,
                'first_name'         => $firstName,
                'middle_name'        => $row['middlename'] ?? null,
                'date_of_birth'      => $dateOfBirth,
                'address'            => $row['address'] ?? null,
                'school_address'     => $row['school_address'] ?? null,
                'shs_school'         => $row['school'] ?? null,
                'strand'             => $row['strand'] ?? null,
                'year_graduated'     => $row['year_graduated'] ?? null,
                'reference_number'   => $referenceNumber,
                'batch_number'       => $this->batch,
                'school_year'        => $this->schoolYear,
                'pupcet_total_score' => $pupcetScore,
                'user_id'            => $user?->id,
                'status'             => $user ? 'registered' : 'pending',
                'admission_type'     => $admissionType,
            ]
        );
    }

    /**
     * Resolve the PUPCET total score from the row, accepting several column
     * name variants that Excel users might use.
     *
     * WithHeadingRow normalises headers to lowercase_with_underscores, so:
     *   "PUPCET Total Score" → pupcet_total_score  ✓
     *   "pupcet total score" → pupcet_total_score  ✓
     *   "PUPCET Score"       → pupcet_score        ✓
     *   "Total Score"        → total_score         ✓
     */
    private function resolveScore(array $row): ?float
    {
        $candidates = ['pupcet_total_score', 'pupcet_score', 'total_score'];

        foreach ($candidates as $key) {
            if (array_key_exists($key, $row) && $row[$key] !== null && $row[$key] !== '') {
                if (is_numeric($row[$key])) {
                    return (float) $row[$key];
                }
            }
        }

        return null;
    }

    private function resolveAdmissionType(array $row): string
    {
        $candidates = ['type', 'remarks', 'status', 'admission_type', 'admission type'];

        foreach ($candidates as $key) {
            if (array_key_exists($key, $row) && !empty($row[$key])) {
                $val = strtolower(trim($row[$key]));
                if (str_contains($val, 'waitlist') || str_contains($val, 'wait-list') || str_contains($val, 'waiting list') || str_contains($val, 'waiting')) {
                    return 'waitlisted';
                }
                if (str_contains($val, 'pass')) {
                    return 'passer';
                }
            }
        }

        return 'passer';
    }
}
