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

    $email = $row['email'] ?? null;
    $referenceNumber = $row['reference_number'] ?? null;

    // If a user with this email already exists, automatically link them and assign student number
    if ($email) {
        $user = User::where('email', $email)->first();
        if ($user && $user->applicantProfile) {
            $user->applicantProfile->update(['student_number' => $referenceNumber]);
        }
    }

    return TestPasser::updateOrCreate(
        ['email' => $email],
        [
            'surname' => $row['surname'] ?? null,
            'first_name' => $firstName,
            'middle_name' => $row['middlename'] ?? null,
            'date_of_birth' => isset($row['date_of_birth']) ? date('Y-m-d', strtotime($row['date_of_birth'])) : null,
            'address' => $row['address'] ?? null,
            'school_address' => $row['school_address'] ?? null,
            'shs_school' => $row['school'] ?? null,
            'strand' => $row['strand'] ?? null,
            'year_graduated' => $row['year_graduated'] ?? null,
            'reference_number' => $referenceNumber,
            'batch_number' => $this->batch,
            'school_year' => $this->schoolYear,
            'user_id' => $user->id ?? null,
            'status' => $user ? 'registered' : 'pending'
        ]
    );
}

}
