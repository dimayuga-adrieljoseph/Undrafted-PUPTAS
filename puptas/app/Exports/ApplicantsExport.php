<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Illuminate\Database\Eloquent\Builder;

class ApplicantsExport implements FromQuery, WithMapping, WithHeadings
{
    protected $query;

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
            'Student Number',
            'Name',
            'Program',
            'Status',
            'Date',
        ];
    }

    public function map($app): array
    {
        return [
            $this->sanitizeExcelValue($app->user->student_number ?? 'N/A'),
            $this->sanitizeExcelValue(trim(($app->user->firstname ?? '') . ' ' . ($app->user->lastname ?? ''))),
            $this->sanitizeExcelValue($app->program->code ?? 'N/A'),
            $this->sanitizeExcelValue($this->determineStatus($app)),
            $this->sanitizeExcelValue($app->updated_at->format('Y-m-d'))
        ];
    }

    private function sanitizeExcelValue($value)
    {
        if (is_string($value) && in_array(substr($value, 0, 1), ['=', '+', '-', '@'])) {
            return "'" . $value;
        }
        return $value;
    }

    private function determineStatus($application)
    {
        if ($application->enrollment_status === 'officially_enrolled') {
            return 'Enrolled';
        }

        $medical = $application->processes->where('stage', 'medical')->where('status', 'completed')->first();
        if ($medical) {
            return 'Medical Cleared';
        }

        $interview = $application->processes->where('stage', 'interviewer')->where('status', 'completed')->sortByDesc('created_at')->first();
        if ($interview) {
            if ($interview->action === 'transferred') {
                return 'Interview Finished (Transferred)';
            }
            return 'Interview Finished (Passed)';
        }

        return ucfirst(str_replace('_', ' ', $application->status));
    }
}
