<?php

namespace App\Services;

use App\Models\Application;
use App\Models\Program;

/**
 * KPI Service
 *
 * Computes all four Key Performance Indicators from live admissions data.
 * This service is read-only and performs no mutations to application or enrollment data.
 */
class KpiService
{
    /**
     * Total system-wide capacity (slots) used as the Slot Utilization denominator.
     */
    public const CAPACITY_LIMIT = 550;

    /**
     * Defined numeric targets for each KPI (all as percentages).
     */
    public const TARGETS = [
        'enrollment_rate'         => 70.0,
        'slot_utilization'        => 60.0,
        'document_conversion'     => 80.0,
        'per_program_utilization' => 50.0,
        'medical_clearance_rate'  => 85.0,
        'completion_rate'         => 60.0,
        'pullout_rate'            => 10.0,  // lower is better — met when value <= target
        'return_rate'             => 15.0,  // lower is better — met when value <= target
    ];

    /**
     * Compute all KPIs from live database data.
     *
     * Returns an array with:
     *   - 'kpis'        => array of four KPI result objects
     *   - 'per_program' => array of per-program breakdown entries
     *
     * @return array{kpis: array, per_program: array}
     */
    public function compute(): array
    {
        // ── Enrollment Rate ──────────────────────────────────────────────
        // Numerator: applicants with enrollment_status = 'officially_enrolled'
        $enrolledCount = Application::where('enrollment_status', 'officially_enrolled')->count();

        // Denominator: applicants who passed the interviewer stage
        $interviewPassedCount = Application::whereHas('processes', fn ($q) =>
            $q->where('stage', 'interviewer')
              ->where('status', 'completed')
              ->where('action', 'passed')
        )->count();

        $enrollmentRateValue = $this->safeDivide($enrolledCount, $interviewPassedCount);

        $enrollmentRateKpi = $this->buildKpi(
            'enrollment_rate',
            'Enrollment Rate',
            $enrollmentRateValue,
            self::TARGETS['enrollment_rate'],
            '%'
        );

        // ── Slot Utilization Rate ─────────────────────────────────────────
        // Denominator is the constant CAPACITY_LIMIT (550)
        $slotUtilizationValue = $this->safeDivide($enrolledCount, self::CAPACITY_LIMIT);

        $slotUtilizationKpi = $this->buildKpi(
            'slot_utilization',
            'Slot Utilization Rate',
            $slotUtilizationValue,
            self::TARGETS['slot_utilization'],
            '%'
        );

        // ── Document Conversion Rate ──────────────────────────────────────
        // Numerator: applications that have a completed document_evaluator stage process
        $docEvaluatedCount = Application::whereHas('processes', fn ($q) =>
            $q->where('stage', 'document_evaluator')
              ->where('status', 'completed')
        )->count();

        // Denominator: submitted applications (not draft, with submitted_at set)
        $submittedCount = Application::where('status', '!=', 'draft')
            ->whereNotNull('submitted_at')
            ->count();

        $documentConversionValue = $this->safeDivide($docEvaluatedCount, $submittedCount);

        $documentConversionKpi = $this->buildKpi(
            'document_conversion',
            'Document Conversion Rate',
            $documentConversionValue,
            self::TARGETS['document_conversion'],
            '%'
        );

        // ── Per-Program Slot Utilization ──────────────────────────────────
        $programs = Program::where('slots', '>', 0)->get();

        $perProgramBreakdown = [];
        $programValues       = [];

        foreach ($programs as $program) {
            $programEnrolled = Application::where('program_id', $program->id)
                ->where('enrollment_status', 'officially_enrolled')
                ->count();

            $programValue = $this->safeDivide($programEnrolled, (int) $program->slots);

            $perProgramBreakdown[] = [
                'code'     => $program->code,
                'name'     => $program->name,
                'slots'    => (int) $program->slots,
                'enrolled' => $programEnrolled,
                'value'    => $programValue,
            ];

            $programValues[] = $programValue;
        }

        // Per-program KPI value = average utilization across qualifying programs
        $perProgramAvg = count($programValues) > 0
            ? round(array_sum($programValues) / count($programValues), 2)
            : 0.0;

        // Per-program KPI met = true only if ALL programs individually meet the 50% target
        $perProgramMet = count($programValues) > 0 && array_reduce(
            $perProgramBreakdown,
            fn (bool $carry, array $entry) => $carry && ($entry['value'] >= self::TARGETS['per_program_utilization']),
            true
        );

        $perProgramKpi = $this->buildKpi(
            'per_program_utilization',
            'Per-Program Slot Utilization',
            $perProgramAvg,
            self::TARGETS['per_program_utilization'],
            '%'
        );

        // Override the `met` field with the all-programs-pass logic
        $perProgramKpi['met'] = $perProgramMet;

        // Attach the breakdown to the per-program KPI object
        $perProgramKpi['breakdown'] = $perProgramBreakdown;

        // ── Medical Clearance Rate ────────────────────────────────────────
        // Numerator: applications that have a completed medical stage with action 'passed'
        $medicalPassedCount = Application::whereHas('processes', fn ($q) =>
            $q->where('stage', 'medical')
              ->where('status', 'completed')
              ->where('action', 'passed')
        )->count();

        // Denominator: applications that have ever reached the medical stage (any status)
        $medicalReachedCount = Application::whereHas('processes', fn ($q) =>
            $q->where('stage', 'medical')
        )->count();

        $medicalClearanceValue = $this->safeDivide($medicalPassedCount, $medicalReachedCount);

        $medicalClearanceKpi = $this->buildKpi(
            'medical_clearance_rate',
            'Medical Clearance Rate',
            $medicalClearanceValue,
            self::TARGETS['medical_clearance_rate'],
            '%'
        );

        // ── Application Completion Rate ───────────────────────────────────
        // Numerator: applications that reached cleared_for_enrollment or officially_enrolled
        $completedCount = Application::where(function ($q) {
            $q->whereIn('status', ['cleared_for_enrollment', 'accepted'])
              ->orWhere('enrollment_status', 'officially_enrolled');
        })->count();

        // Denominator: all submitted applications (non-draft)
        $completionValue = $this->safeDivide($completedCount, $submittedCount);

        $completionRateKpi = $this->buildKpi(
            'completion_rate',
            'Application Completion Rate',
            $completionValue,
            self::TARGETS['completion_rate'],
            '%'
        );

        // ── Pull-out Rate ─────────────────────────────────────────────────
        // Numerator: applications that were pulled out (interviewer in_progress, null action,
        //   has decision_reason or reviewer_notes, and no medical/records processes).
        $pulloutCount = Application::whereHas('processes', fn ($q) =>
            $q->where('stage', 'interviewer')
              ->where('status', 'in_progress')
              ->whereNull('action')
              ->where(fn ($q2) =>
                  $q2->whereNotNull('decision_reason')
                     ->orWhereNotNull('reviewer_notes')
              )
        )->whereDoesntHave('processes', fn ($q) =>
            $q->whereIn('stage', ['medical', 'records'])
        )->count();

        // Denominator: applicants who passed the interviewer stage
        $pulloutRateValue = $this->safeDivide($pulloutCount, $interviewPassedCount);

        // Pull-out rate: lower is better — met when value <= target
        $pulloutRateKpi = $this->buildKpiLowerIsBetter(
            'pullout_rate',
            'Pull-out Rate',
            $pulloutRateValue,
            self::TARGETS['pullout_rate'],
            '%'
        );

        // ── Return Rate ───────────────────────────────────────────────────
        // Numerator: applications with status = 'returned'
        $returnedCount = Application::where('status', 'returned')->count();

        // Denominator: all submitted applications (non-draft)
        $returnRateValue = $this->safeDivide($returnedCount, $submittedCount);

        // Return rate: lower is better — met when value <= target
        $returnRateKpi = $this->buildKpiLowerIsBetter(
            'return_rate',
            'Return Rate',
            $returnRateValue,
            self::TARGETS['return_rate'],
            '%'
        );

        return [
            'kpis'        => [
                $enrollmentRateKpi,
                $slotUtilizationKpi,
                $documentConversionKpi,
                $perProgramKpi,
                $medicalClearanceKpi,
                $completionRateKpi,
                $pulloutRateKpi,
                $returnRateKpi,
            ],
            'per_program' => $perProgramBreakdown,
        ];
    }

    /**
     * Safely divide numerator by denominator, returning 0.0 when denominator is zero.
     *
     * Made public to allow direct unit testing without reflection.
     *
     * @param int   $numerator
     * @param int   $denominator
     * @param float $multiplier  Defaults to 100.0 for percentage calculations.
     * @return float  Result rounded to 2 decimal places, or 0.0 if denominator is zero.
     */
    public function safeDivide(int $numerator, int $denominator, float $multiplier = 100.0): float
    {
        if ($denominator === 0) {
            return 0.0;
        }

        return round(($numerator / $denominator) * $multiplier, 2);
    }

    /**
     * Build a single KPI result array from its components.
     *
     * @param string $id     Snake-case identifier for this KPI.
     * @param string $label  Human-readable display label.
     * @param float  $value  Computed percentage value (already rounded to 2dp).
     * @param float  $target Numeric target percentage.
     * @param string $unit   Unit string (e.g. '%').
     * @return array KPI result object with id, label, value, target, unit, met.
     */
    public function buildKpi(string $id, string $label, float $value, float $target, string $unit): array
    {
        return [
            'id'     => $id,
            'label'  => $label,
            'value'  => $value,
            'target' => $target,
            'unit'   => $unit,
            'met'    => $value >= $target,
        ];
    }

    /**
     * Build a KPI result for "lower is better" metrics (e.g. pull-out rate, return rate).
     * `met` is true when the computed value is at or below the target threshold.
     *
     * @param string $id
     * @param string $label
     * @param float  $value
     * @param float  $target  Maximum acceptable value (threshold).
     * @param string $unit
     * @return array KPI result object with lowerIsBetter = true.
     */
    public function buildKpiLowerIsBetter(string $id, string $label, float $value, float $target, string $unit): array
    {
        return [
            'id'             => $id,
            'label'          => $label,
            'value'          => $value,
            'target'         => $target,
            'unit'           => $unit,
            'met'            => $value <= $target,
            'lowerIsBetter'  => true,
        ];
    }
}
