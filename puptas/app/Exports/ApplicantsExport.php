<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Illuminate\Database\Eloquent\Builder;
use App\Services\ApplicationStatusService;

class ApplicantsExport implements FromQuery, WithMapping, WithHeadings
{
    protected $query;
    protected ApplicationStatusService $statusService;

    public function __construct(Builder $query, ApplicationStatusService $statusService)
    {
        $this->query = $query;
        $this->statusService = $statusService;
    }

    public function query()
    {
        return $this->query;
    }

    public function headings(): array
    {
        return [
            'Reference Number',
            'Name',
            'Program',
            'Status',
            'Date',
        ];
    }

    public function map($app): array
    {
        return [
            $this->sanitizeExcelValue($app->user->testPasser->reference_number ?? 'N/A'),
            $this->sanitizeExcelValue(trim(($app->user->firstname ?? '') . ' ' . ($app->user->lastname ?? ''))),
            $this->sanitizeExcelValue($app->program->code ?? 'N/A'),
            $this->sanitizeExcelValue($this->statusService->determineStatus($app)),
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
}
