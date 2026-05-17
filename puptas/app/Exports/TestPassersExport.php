<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Illuminate\Database\Eloquent\Builder;

class TestPassersExport implements FromQuery, WithMapping, WithHeadings
{
    protected $query;
    protected $rowNumber = 0;

    public function __construct(Builder $query)
    {
        $this->query = $query;
    }

    public function query()
    {
        return $this->query;
    }

    public function headings(): array
    {
        return [
            'Rank',
            'Reference Number',
            'Name',
            'Email',
            'Strand',
            'Score',
            'Status'
        ];
    }

    public function map($passer): array
    {
        $this->rowNumber++;
        $fullName = trim($passer->first_name . ' ' . $passer->middle_name . ' ' . $passer->surname);

        return [
            $this->rowNumber,
            $this->sanitizeExcelValue($passer->reference_number ?? 'N/A'),
            $this->sanitizeExcelValue($fullName),
            $this->sanitizeExcelValue($passer->email ?? 'N/A'),
            $this->sanitizeExcelValue($passer->strand ?? 'N/A'),
            $passer->pupcet_total_score,
            ucfirst($passer->admission_type),
        ];
    }

    private function sanitizeExcelValue($value)
    {
        if (is_string($value) && in_array(substr($value, 0, 1), ['=', '+', '-', '@'])) {
            return "'" . $value;
        }
        return $value;
    }
}
