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
     * Returns true if:
     * 1. The strand is in the program's allowed strands (or program allows all strands)
     * 2. Each average meets or exceeds the corresponding program threshold
     *    (null/zero threshold means no requirement for that category)
     *
     * If any average is null, the program is not qualified.
     *
     * @param Program $program
     * @param string $strand
     * @param float|null $mathAvg
     * @param float|null $englishAvg
     * @param float|null $scienceAvg
     * @param float|null $gwa
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
        // If any average is null, the program is not qualified
        if ($mathAvg === null || $englishAvg === null || $scienceAvg === null || $gwa === null) {
            return false;
        }

        // Check strand eligibility
        if (!$this->isStrandAllowed($program, $strand)) {
            return false;
        }

        // Check each threshold - null/zero threshold means no requirement
        if (!empty($program->math) && $program->math > 0 && $mathAvg < $program->math) {
            return false;
        }

        if (!empty($program->english) && $program->english > 0 && $englishAvg < $program->english) {
            return false;
        }

        if (!empty($program->science) && $program->science > 0 && $scienceAvg < $program->science) {
            return false;
        }

        if (!empty($program->gwa) && $program->gwa > 0 && $gwa < $program->gwa) {
            return false;
        }

        return true;
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
     * A program allows a strand if:
     * - The program has no strand restrictions (empty strands relationship means open to all)
     * - OR the strand code is in the program's associated strands
     */
    private function isStrandAllowed(Program $program, string $strand): bool
    {
        $allowedStrands = $program->strands;

        // If no strands are defined, the program is open to all
        if ($allowedStrands->isEmpty()) {
            return true;
        }

        $strandCode = strtoupper($strand);

        return $allowedStrands->contains(function ($s) use ($strandCode) {
            return strtoupper($s->code) === $strandCode;
        });
    }
}
