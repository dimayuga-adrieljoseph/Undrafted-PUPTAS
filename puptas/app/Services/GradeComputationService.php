<?php

namespace App\Services;

use App\Models\Program;

class GradeComputationService
{
    /**
     * Compute category average from individual grades and dynamic subjects.
     *
     * Only includes:
     * - Valid numeric values (0-100) from individual grades
     * - Dynamic subjects where name has at least one non-whitespace character
     *   AND grade is a valid numeric value (0-100)
     *
     * Returns the arithmetic mean rounded to 2 decimal places,
     * or null if no valid values exist.
     *
     * @param array $individualGrades Array of numeric values (or nulls) from default subject fields
     * @param array $dynamicSubjects Array of objects with 'name' and 'grade' keys
     * @return float|null
     */
    public function computeCategoryAverage(array $individualGrades, array $dynamicSubjects): ?float
    {
        $validValues = [];

        // Collect valid numeric values from individual grades
        foreach ($individualGrades as $grade) {
            if ($this->isValidGrade($grade)) {
                $validValues[] = (float) $grade;
            }
        }

        // Collect valid grades from dynamic subjects
        // Only include entries where name has at least one non-whitespace character
        // AND grade is a valid numeric value (0-100)
        foreach ($dynamicSubjects as $entry) {
            $name = $entry['name'] ?? '';
            $grade = $entry['grade'] ?? null;

            // Name must contain at least one non-whitespace character
            if (!$this->hasNonWhitespace($name)) {
                continue;
            }

            // Grade must be valid numeric in range [0, 100]
            if ($this->isValidGrade($grade)) {
                $validValues[] = (float) $grade;
            }
        }

        if (empty($validValues)) {
            return null;
        }

        $mean = array_sum($validValues) / count($validValues);

        return round($mean, 2);
    }

    /**
     * Check if a user qualifies for a program given their averages and strand.
     *
     * Mirrors the Qualified Programs page logic exactly so both surfaces always agree:
     * - GWA is recomputed from semester fields (not the stored gwa column)
     * - Null averages are treated as 0 (PHP loose comparison, same as QP page)
     * - Threshold of null/0 means no requirement
     *
     * @param Program $program
     * @param string $strand
     * @param float|null $mathAvg
     * @param float|null $englishAvg
     * @param float|null $scienceAvg
     * @param float|null $gwa   GWA recomputed from (g12_first_sem + g12_second_sem) / 2
     * @return bool
     */
    public function isQualified(
        Program $program,
        string $strand,
        ?float $mathAvg,
        ?float $englishAvg,
        ?float $scienceAvg,
        ?float $gwa
    ): bool {
        // Check strand eligibility
        if (!$this->isStrandAllowed($program, $strand)) {
            return false;
        }

        // Grade threshold checks — null treated as 0 (matches Qualified Programs page).
        // ($value ?? 0) on the program side handles null/missing thresholds.
        $meetsGrades = ($mathAvg    ?? 0) >= ($program->math    ?? 0) &&
                       ($scienceAvg ?? 0) >= ($program->science ?? 0) &&
                       ($englishAvg ?? 0) >= ($program->english ?? 0) &&
                       ($gwa        ?? 0) >= ($program->gwa     ?? 0);

        return $meetsGrades;
    }

    /**
     * Check if a grade value is valid (numeric and within 0-100 range).
     */
    private function isValidGrade($grade): bool
    {
        if ($grade === null || $grade === '' || $grade === false) {
            return false;
        }

        if (!is_numeric($grade)) {
            return false;
        }

        $numericGrade = (float) $grade;

        return $numericGrade >= 0 && $numericGrade <= 100;
    }

    /**
     * Check if a string contains at least one non-whitespace character.
     */
    private function hasNonWhitespace(string $value): bool
    {
        return preg_match('/\S/', $value) === 1;
    }

    /**
     * Check if the given strand is allowed for the program.
     *
     * Uses the strand_names accessor (derived from the strands pivot) as a
     * comma/slash-separated string — the same logic as the Qualified Programs
     * page — so both surfaces always agree.
     *
     * Rules (in order):
     * 1. No strand info on the applicant → allow all
     * 2. No strand requirement on the program (empty strand_names) → allow all
     * 3. Program explicitly says "OPEN TO ALL" → allow all
     * 4. "OTHER" + "BRIDGING" in strand_names → allow all
     * 5. Applicant strand must appear in the comma/slash-separated allowed list
     *    (TVL / TECH-VOC are normalised to "TVL" before comparison)
     */
    private function isStrandAllowed(Program $program, string $strand): bool
    {
        if (!$strand) {
            return true;
        }

        // strand_names is the accessor that joins strand codes from the pivot
        $strandNames = strtoupper($program->strand_names ?? '');

        if (empty($strandNames)) {
            return true;
        }

        if (str_contains($strandNames, 'OPEN TO ALL')) {
            return true;
        }

        $userStrand = strtoupper($strand);

        $allowedStrands = array_map('trim', preg_split('/[,\/]/', $strandNames));

        foreach ($allowedStrands as $allowed) {
            if (str_contains($allowed, 'TECH-VOC') || str_contains($allowed, 'TVL')) {
                $allowed = 'TVL';
            }

            if ($allowed === $userStrand) {
                return true;
            }
        }

        if (str_contains($strandNames, 'OTHER') && str_contains($strandNames, 'BRIDGING')) {
            return true;
        }

        return false;
    }
}
