<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Illuminate\Database\Eloquent\Builder;

class MasterlistExport implements FromQuery, WithMapping, WithHeadings
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
            '#',
            'Student Number',
            'Reference Number',
            'Name',
            'Email',
            'Accepted Program',
            'Date Accepted',
        ];
    }

    public function map($app): array
    {
        $this->rowNumber++;
        $fullName = trim(($app->user->firstname ?? '') . ' ' . ($app->user->lastname ?? ''));
        
        $interviewProcess = $app->processes
            ->where('stage', 'interviewer')
            ->where('status', 'completed')
            ->where('action', 'passed')
            ->first();
        $dateAccepted = $interviewProcess ? $interviewProcess->updated_at->format('Y-m-d') : $app->updated_at->format('Y-m-d');

        return [
            $this->rowNumber,
            $this->sanitizeExcelValue($app->user->student_number ?? 'N/A'),
            $this->sanitizeExcelValue($app->user->testPasser->reference_number ?? 'N/A'),
            $this->sanitizeExcelValue($fullName),
            $this->sanitizeExcelValue($app->user->email ?? 'N/A'),
            $this->sanitizeExcelValue($app->program->code ?? 'N/A'),
            $dateAccepted,
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
