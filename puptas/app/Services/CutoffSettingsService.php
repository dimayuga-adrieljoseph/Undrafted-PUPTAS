<?php

namespace App\Services;

use App\Models\CutoffSettings;
use App\Models\SystemSetting;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * CutoffSettingsService
 *
 * Encapsulates all business logic for the application submission cutoff.
 * The cutoff is stored as a single nullable timestamp in the `cutoff_settings`
 * table (singleton row, ID = 1). All datetime comparisons use Asia/Manila (PHT, UTC+8).
 *
 * Never query CutoffSettings directly — always go through this service.
 */
class CutoffSettingsService
{
    private const TIMEZONE = 'Asia/Manila';
    private const SINGLETON_ID = 1;

    /**
     * Get the current cutoff datetime as a CarbonImmutable in Asia/Manila,
     * or null if no cutoff is configured.
     *
     * @return CarbonImmutable|null
     */
    public function getCutoff(): ?CarbonImmutable
    {
        $record = CutoffSettings::first();

        if (! $record || $record->cutoff_at === null) {
            return null;
        }

        // The model casts cutoff_at to 'immutable_datetime', which returns a
        // CarbonImmutable. Ensure it is expressed in Asia/Manila.
        /** @var CarbonImmutable $cutoff */
        $cutoff = $record->cutoff_at;

        return $cutoff->setTimezone(self::TIMEZONE);
    }

    /**
     * Parse and save a new cutoff datetime to the singleton row.
     *
     * Accepts any string that PHP/Carbon can parse. If the string carries no
     * timezone offset, it is interpreted as an Asia/Manila local time.
     *
     * Validation rule: the supplied datetime must be at least 1 minute ahead of
     * Carbon::now('Asia/Manila'). Throws ValidationException on failure.
     *
     * @param  string $datetime  Raw datetime string (e.g. "2026-05-30T23:59" or "2026-05-30T23:59:00+08:00")
     * @return CutoffSettings    The updated singleton record
     * @throws ValidationException
     */
    public function saveCutoff(string $datetime): CutoffSettings
    {
        // Parse the input, interpreting naive strings as Asia/Manila.
        $parsed = $this->parseAsManila($datetime);

        // Validate: must be at least 1 minute in the future.
        $threshold = CarbonImmutable::now(self::TIMEZONE)->addMinute();

        if ($parsed->lt($threshold)) {
            $validator = Validator::make([], []);
            $validator->errors()->add(
                'cutoff_at',
                'The cutoff must be a future datetime (at least 1 minute from now).'
            );
            throw new ValidationException($validator);
        }

        $utcDateTime = $parsed->clone()->utc()->toDateTimeString();

        $record = CutoffSettings::first();
        if (!$record) {
            $record = CutoffSettings::create(['cutoff_at' => $utcDateTime]);
        } else {
            $record->update(['cutoff_at' => $utcDateTime]);
        }

        return $record->refresh();
    }

    /**
     * Clear the cutoff by setting cutoff_at to null on the singleton row.
     *
     * This is a no-op when the cutoff is already null — the record is still
     * returned so callers do not need to handle null.
     *
     * @return CutoffSettings  The updated (or unchanged) singleton record
     */
    public function clearCutoff(): CutoffSettings
    {
        $record = CutoffSettings::first();
        
        if (!$record) {
            return CutoffSettings::create(['cutoff_at' => null]);
        }

        // No-op when already null — skip the write.
        if ($record->cutoff_at !== null) {
            $record->update(['cutoff_at' => null]);
            $record->refresh();
        }

        return $record;
    }

    /**
     * Determine whether the submission period has closed.
     *
     * Returns true when cutoff_at is not null AND the current Asia/Manila time
     * is at or past the cutoff. Returns false in all other cases, including
     * when no cutoff is configured.
     *
     * @return bool
     */
    public function isCutoffPassed(): bool
    {
        $cutoff = $this->getCutoff();

        if ($cutoff === null) {
            return false;
        }

        return CarbonImmutable::now(self::TIMEZONE)->gte($cutoff);
    }

    /**
     * Format the current cutoff for human-readable display.
     *
     * Returns a string like "May 30, 2026, 11:59 PM PHT" or null when no
     * cutoff is configured.
     *
     * @return string|null
     */
    public function formatForDisplay(): ?string
    {
        $cutoff = $this->getCutoff();

        if ($cutoff === null) {
            return null;
        }

        // "May 30, 2026, 11:59 PM PHT"
        return $cutoff->format('F j, Y, g:i A') . ' PHT';
    }

    // ─── Helpers ────────────────────────────────────────────────────────────────

    /**
     * Parse a datetime string into a CarbonImmutable in Asia/Manila.
     *
     * When the string already carries a timezone offset (Z, ±HH:MM, ±HHMM, ±HH)
     * it is parsed as-is and then converted to Asia/Manila. Naive strings are
     * parsed directly in the Asia/Manila timezone context.
     *
     * @param  string $value
     * @return CarbonImmutable
     */
    private function parseAsManila(string $value): CarbonImmutable
    {
        if ($this->hasTimezoneOffset($value)) {
            return CarbonImmutable::parse($value)->setTimezone(self::TIMEZONE);
        }

        return CarbonImmutable::parse($value, self::TIMEZONE);
    }

    /**
     * Determine whether the raw datetime string already carries a timezone offset.
     *
     * Matches suffixes like: Z, +08:00, -05:00, +0800, +08
     *
     * @param  string $value
     * @return bool
     */
    private function hasTimezoneOffset(string $value): bool
    {
        return (bool) preg_match('/[Zz]$|[+-]\d{2}:?\d{0,2}$/', trim($value));
    }

    // ─── Registration Score Range Overrides ─────────────────────────────────────

    /**
     * Get all score range override entries.
     * Each entry: ['id' => string, 'score_from' => float, 'score_to' => float, 'expires_at' => string|null]
     *
     * Backwards-compatible: migrates legacy single-score entries on the fly.
     *
     * @return array
     */
    public function getAllowedRegistrationScores(): array
    {
        $setting = SystemSetting::where('key', 'allowed_registration_scores')->first();
        if (!$setting || empty($setting->value)) {
            return [];
        }

        $decoded = json_decode($setting->value, true);
        if (!is_array($decoded)) {
            return [];
        }

        return array_values(array_map(function ($item, $index) {
            // Legacy: flat numeric value e.g. 85.5
            if (is_numeric($item)) {
                return [
                    'id'         => 'legacy_' . $index,
                    'score_from' => (float) $item,
                    'score_to'   => (float) $item,
                    'expires_at' => null,
                ];
            }

            // Legacy: single-score object { score, expires_at }
            if (isset($item['score']) && !isset($item['score_from'])) {
                return [
                    'id'         => $item['id'] ?? ('legacy_' . $index),
                    'score_from' => (float) $item['score'],
                    'score_to'   => (float) $item['score'],
                    'expires_at' => $item['expires_at'] ?? null,
                ];
            }

            // Current range format
            return [
                'id'         => $item['id'] ?? ('range_' . $index),
                'score_from' => isset($item['score_from']) ? (float) $item['score_from'] : 0.0,
                'score_to'   => isset($item['score_to'])   ? (float) $item['score_to']   : 0.0,
                'expires_at' => $item['expires_at'] ?? null,
            ];
        }, $decoded, array_keys($decoded)));
    }

    /**
     * Check if a specific pupcet_total_score falls within any active range override.
     *
     * @param float $score
     * @return bool
     */
    public function isScoreAllowed(float $score): bool
    {
        $ranges = $this->getAllowedRegistrationScores();

        foreach ($ranges as $item) {
            // Check if score falls within this range (inclusive)
            if ($score >= $item['score_from'] && $score <= $item['score_to']) {
                if (empty($item['expires_at'])) {
                    return true;
                }

                try {
                    $expiresAt = CarbonImmutable::parse($item['expires_at'], self::TIMEZONE);
                    if (CarbonImmutable::now(self::TIMEZONE)->lte($expiresAt)) {
                        return true;
                    }
                } catch (\Exception $e) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Add a new score range override entry.
     *
     * @param float       $scoreFrom
     * @param float       $scoreTo
     * @param string|null $expiresAt
     * @return void
     */
    public function addAllowedRegistrationScore(float $scoreFrom, float $scoreTo, ?string $expiresAt = null): void
    {
        $ranges = $this->getAllowedRegistrationScores();

        $expiresAtManila = null;
        if ($expiresAt) {
            $expiresAtManila = CarbonImmutable::parse($expiresAt, self::TIMEZONE)->toDateTimeString();
        }

        $ranges[] = [
            'id'         => 'range_' . uniqid(),
            'score_from' => $scoreFrom,
            'score_to'   => $scoreTo,
            'expires_at' => $expiresAtManila,
        ];

        SystemSetting::updateOrCreate(
            ['key' => 'allowed_registration_scores'],
            ['value' => json_encode(array_values($ranges))]
        );
    }

    /**
     * Update an existing score range override by its ID.
     *
     * @param string      $id
     * @param float       $scoreFrom
     * @param float       $scoreTo
     * @param string|null $expiresAt
     * @return bool  Returns false when the ID is not found.
     */
    public function updateAllowedRegistrationScore(string $id, float $scoreFrom, float $scoreTo, ?string $expiresAt = null): bool
    {
        $ranges = $this->getAllowedRegistrationScores();
        $found  = false;

        $expiresAtManila = null;
        if ($expiresAt) {
            $expiresAtManila = CarbonImmutable::parse($expiresAt, self::TIMEZONE)->toDateTimeString();
        }

        $updated = array_map(function ($item) use ($id, $scoreFrom, $scoreTo, $expiresAtManila, &$found) {
            if ($item['id'] === $id) {
                $found = true;
                return [
                    'id'         => $id,
                    'score_from' => $scoreFrom,
                    'score_to'   => $scoreTo,
                    'expires_at' => $expiresAtManila,
                ];
            }
            return $item;
        }, $ranges);

        if (!$found) {
            return false;
        }

        SystemSetting::updateOrCreate(
            ['key' => 'allowed_registration_scores'],
            ['value' => json_encode(array_values($updated))]
        );

        return true;
    }

    /**
     * Remove a score range override entry by its ID.
     *
     * @param string $id
     * @return void
     */
    public function removeAllowedRegistrationScore(string $id): void
    {
        $ranges   = $this->getAllowedRegistrationScores();
        $filtered = array_filter($ranges, fn($item) => $item['id'] !== $id);

        SystemSetting::updateOrCreate(
            ['key' => 'allowed_registration_scores'],
            ['value' => json_encode(array_values($filtered))]
        );
    }

    // ─── Registration Email Overrides ───────────────────────────────────────────

    /**
     * Get the list of emails allowed to register regardless of cutoff.
     * Each entry: ['email' => string, 'expires_at' => string|null]
     *
     * @return array
     */
    public function getAllowedRegistrationEmails(): array
    {
        $setting = SystemSetting::where('key', 'allowed_registration_emails')->first();
        if (!$setting || empty($setting->value)) {
            return [];
        }

        $decoded = json_decode($setting->value, true);
        if (!is_array($decoded)) {
            return [];
        }

        return array_values(array_map(function ($item) {
            return [
                'email'      => isset($item['email']) ? strtolower(trim($item['email'])) : '',
                'name'       => $item['name'] ?? null,
                'expires_at' => $item['expires_at'] ?? null,
            ];
        }, $decoded));
    }

    /**
     * Check if a specific email is allowed to register regardless of cutoff.
     *
     * @param string $email
     * @return bool
     */
    public function isEmailAllowed(string $email): bool
    {
        $email = strtolower(trim($email));
        $allowed = $this->getAllowedRegistrationEmails();
        
        foreach ($allowed as $item) {
            if ($item['email'] === $email) {
                // Check expiration
                if (empty($item['expires_at'])) {
                    return true; // No expiration means always allowed
                }
                
                // Compare with current Manila time
                try {
                    $expiresAt = CarbonImmutable::parse($item['expires_at'], self::TIMEZONE);
                    if (CarbonImmutable::now(self::TIMEZONE)->lte($expiresAt)) {
                        return true;
                    }
                } catch (\Exception $e) {
                    // Fallback to true if date is unparseable
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Add an email to the allowed registration list.
     *
     * @param string      $email
     * @param string|null $name       Display name (surname, first_name)
     * @param string|null $expiresAt
     * @return void
     */
    public function addAllowedRegistrationEmail(string $email, ?string $name = null, ?string $expiresAt = null): void
    {
        $email   = strtolower(trim($email));
        $allowed = $this->getAllowedRegistrationEmails();

        // Remove any existing entry for this email (upsert behaviour)
        $filtered = array_filter($allowed, fn($item) => $item['email'] !== $email);

        $expiresAtManila = null;
        if ($expiresAt) {
            $expiresAtManila = CarbonImmutable::parse($expiresAt, self::TIMEZONE)->toDateTimeString();
        }

        $filtered[] = [
            'email'      => $email,
            'name'       => $name,
            'expires_at' => $expiresAtManila,
        ];

        SystemSetting::updateOrCreate(
            ['key' => 'allowed_registration_emails'],
            ['value' => json_encode(array_values($filtered))]
        );
    }

    /**
     * Update an existing email override entry (expiry date).
     *
     * @param string      $email
     * @param string|null $expiresAt
     * @return bool
     */
    public function updateAllowedRegistrationEmail(string $email, ?string $expiresAt = null): bool
    {
        $email   = strtolower(trim($email));
        $allowed = $this->getAllowedRegistrationEmails();
        $found   = false;

        $expiresAtManila = null;
        if ($expiresAt) {
            $expiresAtManila = CarbonImmutable::parse($expiresAt, self::TIMEZONE)->toDateTimeString();
        }

        $updated = array_map(function ($item) use ($email, $expiresAtManila, &$found) {
            if ($item['email'] === $email) {
                $found = true;
                return array_merge($item, ['expires_at' => $expiresAtManila]);
            }
            return $item;
        }, $allowed);

        if (!$found) {
            return false;
        }

        SystemSetting::updateOrCreate(
            ['key' => 'allowed_registration_emails'],
            ['value' => json_encode(array_values($updated))]
        );

        return true;
    }

    /**
     * Remove an email from the allowed registration list.
     *
     * @param string $email
     * @return void
     */
    public function removeAllowedRegistrationEmail(string $email): void
    {
        $email    = strtolower(trim($email));
        $allowed  = $this->getAllowedRegistrationEmails();
        $filtered = array_filter($allowed, fn($item) => $item['email'] !== $email);

        SystemSetting::updateOrCreate(
            ['key' => 'allowed_registration_emails'],
            ['value' => json_encode(array_values($filtered))]
        );
    }
}
