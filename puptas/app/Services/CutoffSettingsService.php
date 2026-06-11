<?php

namespace App\Services;

use App\Models\CutoffSettings;
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
        $record = CutoffSettings::find(self::SINGLETON_ID);

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

        $record = CutoffSettings::findOrFail(self::SINGLETON_ID);

        $record->update(['cutoff_at' => $parsed->toDateTimeString()]);

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
        $record = CutoffSettings::findOrFail(self::SINGLETON_ID);

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
}
