<?php

namespace App\Services;

class GradeValidationService
{
    /**
     * Default math subjects shared by all strands.
     */
    private const MATH_BASE = [
        'g11_general_mathematics',
        'g11_statistics_probability',
    ];

    /**
     * Default english subjects shared by all strands.
     */
    private const ENGLISH_BASE = [
        'g11_oral_communication',
        'g11_21st_century_lit',
        'g11_academic_professional',
        'g11_reading_writing',
    ];

    /**
     * Default science subjects shared by all strands.
     */
    private const SCIENCE_BASE = [
        'g11_earth_life_science',
        'g11_physical_science',
    ];

    /**
     * Additional strand-specific subjects per category.
     */
    private const STRAND_ADDITIONS = [
        'STEM' => [
            'math' => ['g11_pre_calculus', 'g11_basic_calculus'],
            'english' => ['g12_academic_professional'],
            'science' => [
                'g11_earth_science',
                'g11_general_chemistry_1',
                'g12_general_physics_1',
                'g12_general_biology_1',
                'g12_general_physics_2',
                'g12_general_biology_2',
                'g12_general_chemistry_2',
            ],
        ],
        'ABM' => [
            'math' => ['g11_business_mathematics'],
            'english' => ['g12_21st_century_lit'],
            'science' => [],
        ],
        'ICT' => [
            'math' => [],
            'english' => [],
            'science' => [],
        ],
        'HUMSS' => [
            'math' => [],
            'english' => [],
            'science' => ['g12_earth_life_science', 'g12_physical_science'],
        ],
        'GAS' => [
            'math' => [],
            'english' => [],
            'science' => [],
        ],
        'TVL' => [
            'math' => [],
            'english' => [],
            'science' => ['g12_earth_life_science', 'g12_physical_science'],
        ],
    ];

    /**
     * Get the list of default subject fields for a given strand and category.
     */
    public function getSubjectsForStrand(string $strand, string $category): array
    {
        $strand = strtoupper($strand);

        $base = match ($category) {
            'math' => self::MATH_BASE,
            'english' => self::ENGLISH_BASE,
            'science' => self::SCIENCE_BASE,
            default => [],
        };

        $additions = self::STRAND_ADDITIONS[$strand][$category] ?? [];

        return array_merge($base, $additions);
    }

    /**
     * Get validation rules for a given strand.
     * All individual subject fields are nullable|numeric|min:0|max:100.
     */
    public function getRules(string $strand): array
    {
        $strand = strtoupper($strand);
        $rules = [];

        $allSubjects = array_merge(
            $this->getSubjectsForStrand($strand, 'math'),
            $this->getSubjectsForStrand($strand, 'english'),
            $this->getSubjectsForStrand($strand, 'science')
        );

        foreach ($allSubjects as $subject) {
            $rules[$subject] = 'nullable|numeric|min:0|max:100';
        }

        return $rules;
    }

    /**
     * Validate dynamic subjects array.
     *
     * Checks:
     * - Each entry has a non-empty name (≤100 chars after trimming)
     * - Each entry has a numeric grade between 0 and 100
     * - Max 5 entries per category
     * - No duplicate names within same category (case-insensitive, trimmed)
     *
     * @param array $dynamicSubjects Array of dynamic subject entries
     * @return array Array of error messages (empty if valid)
     */
    public function validateDynamicSubjects(array $dynamicSubjects): array
    {
        $errors = [];
        $categoryCounts = [];
        $categoryNames = [];

        foreach ($dynamicSubjects as $index => $entry) {
            $category = $entry['category'] ?? '';
            $name = isset($entry['name']) ? trim($entry['name']) : '';
            $grade = $entry['grade'] ?? null;

            // Validate category
            if (!in_array($category, ['math', 'english', 'science'])) {
                $errors[] = "dynamic_subjects.{$index}.category: Invalid category '{$category}'.";
                continue;
            }

            // Track count per category
            if (!isset($categoryCounts[$category])) {
                $categoryCounts[$category] = 0;
            }
            $categoryCounts[$category]++;

            // Check max 5 per category
            if ($categoryCounts[$category] > 5) {
                $errors[] = "dynamic_subjects.{$index}: Maximum 5 dynamic subjects per category ({$category}).";
                continue;
            }

            // Validate name: must be non-empty after trimming and ≤100 chars
            if ($name === '') {
                $errors[] = "dynamic_subjects.{$index}.name: The subject name is required.";
            } elseif (mb_strlen($name) > 100) {
                $errors[] = "dynamic_subjects.{$index}.name: The subject name must not exceed 100 characters.";
            }

            // Check for duplicate names within same category (case-insensitive, trimmed)
            if ($name !== '') {
                $normalizedName = mb_strtolower($name);
                if (!isset($categoryNames[$category])) {
                    $categoryNames[$category] = [];
                }

                if (in_array($normalizedName, $categoryNames[$category])) {
                    $errors[] = "dynamic_subjects.{$index}.name: Duplicate subject name in {$category} category.";
                } else {
                    $categoryNames[$category][] = $normalizedName;
                }
            }

            // Validate grade: must be numeric and between 0 and 100
            if ($grade === null || $grade === '') {
                $errors[] = "dynamic_subjects.{$index}.grade: The grade value is required.";
            } elseif (!is_numeric($grade)) {
                $errors[] = "dynamic_subjects.{$index}.grade: The grade must be a numeric value.";
            } elseif ((float) $grade < 0 || (float) $grade > 100) {
                $errors[] = "dynamic_subjects.{$index}.grade: The grade must be between 0 and 100.";
            }
        }

        return $errors;
    }

    /**
     * Validate that at least one grade is filled per category
     * (Math, English, Science) considering both default fields and dynamic subjects.
     *
     * @param array $data The submitted form data (includes default subject fields)
     * @param string $strand The applicant's strand
     * @return bool True if at least one grade is filled per category, false otherwise
     */
    public function validateMinimumPerCategory(array $data, string $strand): bool
    {
        $strand = strtoupper($strand);
        $dynamicSubjects = $data['dynamic_subjects'] ?? [];

        foreach (['math', 'english', 'science'] as $category) {
            $hasFilledGrade = false;

            // Check default subject fields for this category
            $defaultSubjects = $this->getSubjectsForStrand($strand, $category);
            foreach ($defaultSubjects as $field) {
                if (isset($data[$field]) && is_numeric($data[$field])) {
                    $hasFilledGrade = true;
                    break;
                }
            }

            // If no default field is filled, check dynamic subjects for this category
            if (!$hasFilledGrade) {
                foreach ($dynamicSubjects as $entry) {
                    if (
                        isset($entry['category']) &&
                        $entry['category'] === $category &&
                        isset($entry['name']) &&
                        trim($entry['name']) !== '' &&
                        isset($entry['grade']) &&
                        is_numeric($entry['grade'])
                    ) {
                        $hasFilledGrade = true;
                        break;
                    }
                }
            }

            if (!$hasFilledGrade) {
                return false;
            }
        }

        return true;
    }
}
