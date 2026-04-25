<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserFile;

class DoclingParser
{
    protected const SUBJECT_MAPPING = [
        'math' => [
            'General Mathematics'        => ['general mathematics', 'gen math', 'math', 'mathematics'],
            'Business Mathematics'       => ['business mathematics', 'business math'],
            'Statistics and Probability' => ['statistics and probability', 'statistics', 'stats', 'stat and prob'],
            'Pre-Calculus'               => ['pre-calculus', 'precalculus', 'pre-cal', 'pre cal'],
            'Basic Calculus'             => ['basic calculus', 'basic cal'],
        ],
        'science' => [
            'Earth and Life Science'     => ['earth and life science', 'earth & life science', 'els'],
            'Physical Science'           => ['physical science', 'phys sci'],
            'Earth Science'              => ['earth science', 'earth sci'],
            'General Chemistry 1'        => ['general chemistry 1', 'gen chem 1', 'gen chem', 'chemistry'],
        ],
        'english' => [
            'Oral Communication'         => ['oral communication', 'oral comm'],
            '21st Century Literature'    => ['21st century literature', '21st century lit', '21st lit', '21st century literature from the philippines and the world'],
            'English for Academic Purposes' => ['english for academic purposes', 'eapp'],
            'Reading and Writing'        => ['reading and writing', 'reading & writing'],
        ],
    ];

    /**
     * Parse all UserFile records with non-null docling_json for the given user
     * and return a normalized ExtractionResult.
     *
     * @param  \App\Models\User  $user
     * @return array  ExtractionResult shape
     * @throws \InvalidArgumentException  when no valid subject-grade pairs are found
     */
    public function extract(User $user): array
    {
        $files = UserFile::where('user_id', $user->id)
            ->whereNotNull('docling_json')
            ->orderBy('id', 'asc')
            ->get();

        $accumulator = [];

        foreach ($files as $file) {
            if (empty($file->docling_json)) {
                continue;
            }

            $pairs = $this->parseJsonContent($file->docling_json);
            $accumulator = array_merge($accumulator, $pairs);
        }

        if (empty($accumulator)) {
            throw new \InvalidArgumentException('No valid subject-grade pairs found in Docling JSON.');
        }

        return $this->buildResult($accumulator);
    }

    /**
     * Parse a single docling_json blob (the json_content object) and return
     * a flat map of [ lowercased_subject_name => float_grade ].
     */
    protected function parseJsonContent(array $jsonContent): array
    {
        $result = [];

        foreach ($jsonContent['texts'] ?? [] as $node) {
            $text = $node['text'] ?? $node['orig'] ?? '';
            if ($text === '') {
                continue;
            }
            $pairs = $this->scanTextNode($text);
            $result = array_merge($result, $pairs);
        }

        foreach ($jsonContent['tables'] ?? [] as $table) {
            $pairs = $this->scanTable($table);
            $result = array_merge($result, $pairs);
        }

        return $result;
    }

    /**
     * Scan a single text node string for subject-grade pairs.
     * Returns [ subject_name => float_grade ] or empty array.
     */
    protected function scanTextNode(string $text): array
    {
        $result = [];

        // Primary regex: Subject: <name> Grade: <value>
        if (preg_match_all('/Subject:\s*(.+?)\s+Grade:\s*(\d+(?:\.\d+)?)/i', $text, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $rawSubject = trim($match[1]);
                $rawGrade   = $match[2];

                $grade = $this->validateGrade($rawGrade);
                if ($grade === null) {
                    continue;
                }

                $resolved = $this->resolveSubject($rawSubject);
                $key = $resolved !== null ? $resolved['name'] : $this->normalizeKey($rawSubject);
                $result[$key] = $grade;
            }
        }

        // Secondary scan: known aliases appearing in text followed by a numeric value
        $lines = preg_split('/\r?\n/', $text);
        foreach (self::SUBJECT_MAPPING as $category => $subjects) {
            foreach ($subjects as $canonicalName => $aliases) {
                foreach ($aliases as $alias) {
                    foreach ($lines as $line) {
                        if (stripos($line, $alias) !== false) {
                            // Look for the last numeric value on the line — that's the final grade.
                            // (Earlier numbers may be 1st/2nd sem grades or student IDs.)
                            if (preg_match_all('/(\d+(?:\.\d+)?)/', $line, $numMatches)) {
                                $lastNum = end($numMatches[1]);
                                $grade = $this->validateGrade($lastNum);
                                if ($grade !== null) {
                                    $key = strtolower(trim($canonicalName));
                                    // Only set if not already found by primary regex
                                    if (!isset($result[$key])) {
                                        $result[$key] = $grade;
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }

        return $result;
    }

    /**
     * Scan a Docling table structure for subject-grade pairs.
     * Detects the "Semester Final Grade" (or "Final Grade") column header
     * and reads grades from that column only.
     * Returns [ subject_name => float_grade ] or empty array.
     */
    protected function scanTable(array $table): array
    {
        $result = [];
        $cells  = $table['data']['table_cells'] ?? [];

        // Group cells by row index
        $rows = [];
        foreach ($cells as $cell) {
            $row = $cell['start_row_offset_idx'] ?? $cell['row'] ?? null;
            if ($row === null) {
                $rows[0][] = $cell;
            } else {
                $rows[$row][] = $cell;
            }
        }

        ksort($rows);
        $rowList = array_values($rows);

        // --- Step 1: detect the "Final Grade" column index from header rows ---
        // We scan the first few rows looking for a cell whose text contains
        // "final grade" (case-insensitive). That column index is authoritative.
        $finalGradeColIndex = null;

        foreach (array_slice($rowList, 0, 5) as $headerRow) {
            foreach ($headerRow as $cell) {
                $text = strtolower(trim($cell['text'] ?? ''));
                if (str_contains($text, 'final grade') || $text === 'final') {
                    $finalGradeColIndex = $cell['start_col_offset_idx'] ?? $cell['col'] ?? null;
                    break 2;
                }
            }
        }

        // --- Step 2: scan data rows ---
        foreach ($rowList as $rowCells) {
            // Sort cells by column index so we can rely on order
            usort($rowCells, fn($a, $b) =>
                ($a['start_col_offset_idx'] ?? $a['col'] ?? 0) <=>
                ($b['start_col_offset_idx'] ?? $b['col'] ?? 0)
            );

            // Find the subject cell (first cell that resolves to a known subject)
            $resolved = null;
            $subjectColIndex = null;
            foreach ($rowCells as $cell) {
                $cellText = trim($cell['text'] ?? '');
                $resolved = $this->resolveSubject($cellText);
                if ($resolved !== null) {
                    $subjectColIndex = $cell['start_col_offset_idx'] ?? $cell['col'] ?? null;
                    break;
                }
            }

            if ($resolved === null) {
                continue;
            }

            $grade = null;

            if ($finalGradeColIndex !== null) {
                // Use the detected final grade column
                foreach ($rowCells as $cell) {
                    $colIdx = $cell['start_col_offset_idx'] ?? $cell['col'] ?? null;
                    if ($colIdx === $finalGradeColIndex) {
                        $grade = $this->validateGrade(trim($cell['text'] ?? ''));
                        break;
                    }
                }
            }

            // Fallback: if no header was found or the column had no valid grade,
            // take the last numeric value in the row (excluding Remarks-like text).
            if ($grade === null) {
                foreach (array_reverse($rowCells) as $cell) {
                    $colIdx = $cell['start_col_offset_idx'] ?? $cell['col'] ?? null;
                    // Skip the subject cell itself
                    if ($colIdx === $subjectColIndex) {
                        break;
                    }
                    $candidate = $this->validateGrade(trim($cell['text'] ?? ''));
                    if ($candidate !== null) {
                        $grade = $candidate;
                        break;
                    }
                }
            }

            if ($grade !== null) {
                $result[$resolved['name']] = $grade;
            }
        }

        return $result;
    }

    /**
     * Resolve a raw subject name string to a canonical category key and name.
     * Returns ['category' => string, 'name' => string] or null if not found.
     */
    protected function resolveSubject(string $raw): ?array
    {
        $normalized = strtolower(trim($raw));

        foreach (self::SUBJECT_MAPPING as $category => $subjects) {
            foreach ($subjects as $canonicalName => $aliases) {
                foreach ($aliases as $alias) {
                    if ($normalized === $alias) {
                        return [
                            'category' => $category,
                            'name'     => strtolower(trim($canonicalName)),
                        ];
                    }
                }
            }
        }

        return null;
    }

    /**
     * Validate a raw grade token. Returns float or null if invalid.
     */
    protected function validateGrade(mixed $raw): ?float
    {
        if (!is_numeric($raw)) {
            return null;
        }

        $value = (float) $raw;

        if ($value < 0 || $value > 100) {
            return null;
        }

        return $value;
    }

    /**
     * Wrap a flat [ subject => grade ] map into the ExtractionResult envelope.
     */
    protected function buildResult(array $flat): array
    {
        $result = [
            'subjects' => [
                'math'    => [],
                'science' => [],
                'english' => [],
                'others'  => [],
            ],
        ];

        foreach ($flat as $subjectName => $grade) {
            $resolved = $this->resolveSubject($subjectName);

            if ($resolved !== null) {
                $result['subjects'][$resolved['category']][$resolved['name']] = $grade;
            } else {
                $result['subjects']['others'][$this->normalizeKey($subjectName)] = $grade;
            }
        }

        return $result;
    }

    /**
     * Lowercase and trim a subject name key.
     */
    protected function normalizeKey(string $key): string
    {
        return strtolower(trim($key));
    }
}
