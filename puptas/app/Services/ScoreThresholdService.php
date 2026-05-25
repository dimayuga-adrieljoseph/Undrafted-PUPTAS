<?php

namespace App\Services;

class ScoreThresholdService
{
    /**
     * Maximum combined count of applicants that can hold "Qualified" or "Waitlisted"
     * status within a given school year.
     */
    public const CAPACITY_LIMIT = 550;

    /**
     * Score threshold rules ordered from highest to lowest.
     * Each rule defines: min score (inclusive), max score (exclusive), passer_status_id, batch_number.
     */
    private const THRESHOLDS = [
        ['min' => 85.00, 'max' => null,   'passer_status_id' => 1, 'batch_number' => 'Batch 1'],
        ['min' => 79.00, 'max' => 85.00,  'passer_status_id' => 1, 'batch_number' => 'Batch 2'],
        ['min' => 75.00, 'max' => 79.00,  'passer_status_id' => 2, 'batch_number' => 'Batch 3'],
        ['min' => 55.00, 'max' => 75.00,  'passer_status_id' => 2, 'batch_number' => 'Batch 4'],
        ['min' => null,  'max' => 55.00,  'passer_status_id' => 3, 'batch_number' => null],
    ];

    /**
     * Determine batch assignment from a PUPCET score.
     *
     * When $currentQualifiedWaitlistedCount is provided and >= CAPACITY_LIMIT,
     * scores that would normally be assigned "Waitlisted" (status 2) are instead
     * assigned "Waitlisted Below Cut Off" (status 4) with a null batch_number.
     *
     * @param float $score Valid numeric score
     * @param int|null $currentQualifiedWaitlistedCount Current count of qualified+waitlisted records
     * @return array{passer_status_id: int, batch_number: string|null}
     */
    public function resolve(float $score, ?int $currentQualifiedWaitlistedCount = null): array
    {
        foreach (self::THRESHOLDS as $threshold) {
            $aboveMin = $threshold['min'] === null || $score >= $threshold['min'];
            $belowMax = $threshold['max'] === null || $score < $threshold['max'];

            if ($aboveMin && $belowMax) {
                // If capacity limit reached and score falls in waitlisted range, assign status 4
                if (
                    $threshold['passer_status_id'] === 2
                    && $currentQualifiedWaitlistedCount !== null
                    && $currentQualifiedWaitlistedCount >= self::CAPACITY_LIMIT
                ) {
                    return [
                        'passer_status_id' => 4,
                        'batch_number' => null,
                    ];
                }

                return [
                    'passer_status_id' => $threshold['passer_status_id'],
                    'batch_number' => $threshold['batch_number'],
                ];
            }
        }

        // Fallback (should not be reached with valid thresholds covering all ranges)
        return [
            'passer_status_id' => 3,
            'batch_number' => null,
        ];
    }

    /**
     * Get all threshold rules for display in the UI.
     *
     * Includes score-based threshold rules and the capacity-limit rule.
     *
     * @return array
     */
    public function getRules(): array
    {
        $rules = self::THRESHOLDS;

        $rules[] = [
            'type' => 'capacity_limit',
            'capacity' => self::CAPACITY_LIMIT,
            'passer_status_id' => 4,
            'description' => 'Applicants who would be waitlisted beyond the ' . self::CAPACITY_LIMIT . '-slot capacity will receive "Waitlisted Below Cut Off" status.',
        ];

        return $rules;
    }
}
