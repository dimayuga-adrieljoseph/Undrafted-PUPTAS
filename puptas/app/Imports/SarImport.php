<?php

namespace App\Imports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

/**
 * SAR Excel Import Handler
 * 
 * Parses Excel files containing student admission data for SAR form generation.
 * Expected columns (case-insensitive):
 * - surname, firstname_middle_name (or Firstname + MiddleName)
 * - reference_no, shs_strand, graduation_year, school_previously_attended
 * 
 * PRIVACY: This import handles PII. Do NOT log raw data.
 * Source template: docs/SAR_FORM_TEMPLATE.md
 */
class SarImport implements ToCollection, WithHeadingRow
{
    protected array $parsedRows = [];
    protected array $errors = [];

    /**
     * Process each row from Excel
     */
    public function collection(Collection $rows)
    {
        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2; // Account for header row
            
            try {
                $normalized = $this->normalizeRow($row, $rowNumber);
                $validation = $this->validateRow($normalized, $rowNumber);
                
                if (!empty($validation['errors'])) {
                    $this->errors[] = [
                        'row_index' => $rowNumber,
                        'issues' => $validation['errors']
                    ];
                }
                
                $this->parsedRows[] = array_merge($normalized, [
                    'row_index' => $rowNumber,
                    'errors' => $validation['errors']
                ]);
                
            } catch (\Exception $e) {
                $this->errors[] = [
                    'row_index' => $rowNumber,
                    'issues' => ['Failed to parse row: ' . $e->getMessage()]
                ];
            }
        }
    }

    /**
     * Normalize Excel columns to standardized field names
     */
    protected function normalizeRow($row, int $rowNumber): array
    {
        // Handle various column name formats
        $surname = $this->getColumnValue($row, ['surname', 'last_name', 'family_name']);
        $firstname = $this->getColumnValue($row, ['firstname_middle_name', 'first_name', 'firstname', 'given_name']);
        $middlename = $this->getColumnValue($row, ['middle_name', 'middlename']);
        
        // Combine name parts if needed
        if ($middlename && !str_contains($firstname, $middlename)) {
            $fullFirstMiddle = trim($firstname . ' ' . $middlename);
        } else {
            $fullFirstMiddle = trim($firstname);
        }
        
        $fullName = trim($surname . ', ' . $fullFirstMiddle);
        
        return [
            'id' => uniqid('sar_', true),
            'reference_number' => $this->getColumnValue($row, ['reference_no', 'reference_number', 'ref_no', 'reference']),
            'full_name' => $fullName,
            'surname' => $surname,
            'firstname_middle' => $fullFirstMiddle,
            'shs_strand' => $this->getColumnValue($row, ['shs_strand', 'strand', 'shs_track_strand', 'track_strand']),
            'graduation_year' => $this->getColumnValue($row, ['graduation_year', 'grad_year', 'year_graduated']),
            'school_attended' => $this->getColumnValue($row, ['school_previously_attended', 'previous_school', 'school_attended', 'school']),
            'enrollment_date' => null,
            'enrollment_time' => null,
        ];
    }

    /**
     * Get column value by checking multiple possible header names
     */
    protected function getColumnValue($row, array $possibleKeys): ?string
    {
        foreach ($possibleKeys as $key) {
            $normalizedKey = strtolower(str_replace([' ', '-', '_'], '', $key));
            
            foreach ($row as $rowKey => $value) {
                $normalizedRowKey = strtolower(str_replace([' ', '-', '_'], '', $rowKey));
                
                if ($normalizedRowKey === $normalizedKey) {
                    return trim((string) $value);
                }
            }
        }
        
        return null;
    }

    /**
     * Validate required fields
     */
    protected function validateRow(array $normalized, int $rowNumber): array
    {
        $errors = [];
        
        if (empty($normalized['reference_number'])) {
            $errors[] = 'Missing reference number';
        }
        
        if (empty($normalized['surname']) || empty($normalized['firstname_middle'])) {
            $errors[] = 'Missing name components';
        }
        
        if (empty($normalized['graduation_year'])) {
            $errors[] = 'Missing graduation year';
        } elseif (!preg_match('/^\d{4}$/', $normalized['graduation_year'])) {
            $errors[] = 'Invalid graduation year format (expected 4 digits)';
        }
        
        if (empty($normalized['school_attended'])) {
            $errors[] = 'Missing school previously attended';
        }
        
        if (empty($normalized['shs_strand'])) {
            $errors[] = 'Missing SHS strand';
        }
        
        return ['errors' => $errors];
    }

    /**
     * Get parsed rows with validation results
     */
    public function getParsedRows(): array
    {
        return $this->parsedRows;
    }

    /**
     * Get parsing errors
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Get count of successfully parsed rows
     */
    public function getValidCount(): int
    {
        return count(array_filter($this->parsedRows, fn($row) => empty($row['errors'])));
    }
}
