<?php

namespace App\Services;

class ScoreThresholdService
{
    /**
     * Score threshold rules ordered from highest to lowest.
     * Each rule defines: min score (inclusive), max score (exclusive), passer_status_id, batch_number.
     */
    private const THRESHOLDS = [
        ['min' => 85.00, 'max' => null,  'passer_status_id' => 1, 'batch_number' => 'Batch 1'],
        ['min' => 79.00, 'max' => 85.00, 'passer_status_id' => 1, 'batch_number' => 'Batch 2'],
        ['min' => 75.00, 'max' => 79.00, 'passer_status_id' => 2, 'batch_number' => 'Batch 3'],
        ['min' => 55.00, 'max' => 75.00, 'passer_status_id' => 2, 'batch_number' => 'Batch 4'],
        ['min' => null,  'max' => 55.00, 'passer_status_id' => 3, 'batch_number' => null],
    ];

    /**
     * Determine batch assignment from a PUPCET score.
     *
     * @param float $score Valid numeric score
     * @return array{passer_status_id: int, batch_number: string|null}
     */
    public function resolve(float $score): array
    {
        foreach (self::THRESHOLDS as $threshold) {
            $aboveMin = $threshold['min'] === null || $score >= $threshold['min'];
            $belowMax = $threshold['max'] === null || $score < $threshold['max'];

            if ($aboveMin && $belowMax) {
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
     * @return array
     */
    public function getRules(): array
    {
        return self::THRESHOLDS;
    }
}
