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

    // ─── Registration Score Overrides ───────────────────────────────────────────

    /**
     * Get the list of pupcet_total_scores allowed to register regardless of cutoff.
     *
     * @return float[]
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

        // Migrate flat array [85.5] to [['score' => 85.5, 'expires_at' => null]] on the fly
        return array_map(function ($item) {
            if (is_numeric($item)) {
                return [
                    'score' => (float) $item,
                    'expires_at' => null,
                ];
            }
            return [
                'score' => isset($item['score']) ? (float) $item['score'] : 0.0,
                'expires_at' => $item['expires_at'] ?? null,
            ];
        }, $decoded);
    }

    /**
     * Check if a specific pupcet_total_score is allowed to register regardless of cutoff.
     *
     * @param float $score
     * @return bool
     */
    public function isScoreAllowed(float $score): bool
    {
        $allowed = $this->getAllowedRegistrationScores();
        foreach ($allowed as $item) {
            // Use epsilon for safe float comparison
            if (abs($item['score'] - $score) < 0.001) {
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
                    // Fallback to true if date is unparseable for some reason
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Add a score to the allowed registration list.
     *
     * @param float $score
     * @return void
     */
    public function addAllowedRegistrationScore(float $score, ?string $expiresAt = null): void
    {
        $allowed = $this->getAllowedRegistrationScores();
        
        // Remove any existing entry for this score to avoid duplicates
        $filtered = array_filter($allowed, fn($item) => $item['score'] !== $score);
        
        // Ensure expiration is parsed into Manila time if provided
        $expiresAtManila = null;
        if ($expiresAt) {
            $expiresAtManila = CarbonImmutable::parse($expiresAt, self::TIMEZONE)->toDateTimeString();
        }

        $filtered[] = [
            'score' => $score,
            'expires_at' => $expiresAtManila,
        ];
        
        SystemSetting::updateOrCreate(
            ['key' => 'allowed_registration_scores'],
            ['value' => json_encode(array_values($filtered))]
        );
    }

    /**
     * Remove a score from the allowed registration list.
     *
     * @param float $score
     * @return void
     */
    public function removeAllowedRegistrationScore(float $score): void
    {
        $allowed = $this->getAllowedRegistrationScores();
        $filtered = array_filter($allowed, fn($item) => $item['score'] !== $score);
        
        SystemSetting::updateOrCreate(
            ['key' => 'allowed_registration_scores'],
            ['value' => json_encode(array_values($filtered))]
        );
    }

    // ─── Registration Email Overrides ───────────────────────────────────────────

    /**
     * Get the list of emails allowed to register regardless of cutoff.
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

        return array_map(function ($item) {
            return [
                'email' => isset($item['email']) ? strtolower(trim($item['email'])) : '',
                'expires_at' => $item['expires_at'] ?? null,
            ];
        }, $decoded);
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
     * @param string $email
     * @param string|null $expiresAt
     * @return void
     */
    public function addAllowedRegistrationEmail(string $email, ?string $expiresAt = null): void
    {
        $email = strtolower(trim($email));
        $allowed = $this->getAllowedRegistrationEmails();
        
        // Remove any existing entry for this email
        $filtered = array_filter($allowed, fn($item) => $item['email'] !== $email);
        
        $expiresAtManila = null;
        if ($expiresAt) {
            $expiresAtManila = CarbonImmutable::parse($expiresAt, self::TIMEZONE)->toDateTimeString();
        }

        $filtered[] = [
            'email' => $email,
            'expires_at' => $expiresAtManila,
        ];
        
        SystemSetting::updateOrCreate(
            ['key' => 'allowed_registration_emails'],
            ['value' => json_encode(array_values($filtered))]
        );
    }

    /**
     * Remove an email from the allowed registration list.
     *
     * @param string $email
     * @return void
     */
    public function removeAllowedRegistrationEmail(string $email): void
    {
        $email = strtolower(trim($email));
        $allowed = $this->getAllowedRegistrationEmails();
        $filtered = array_filter($allowed, fn($item) => $item['email'] !== $email);
        
        SystemSetting::updateOrCreate(
            ['key' => 'allowed_registration_emails'],
            ['value' => json_encode(array_values($filtered))]
        );
    }
}
