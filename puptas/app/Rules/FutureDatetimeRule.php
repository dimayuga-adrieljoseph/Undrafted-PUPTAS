<?php

namespace App\Rules;

use Carbon\Carbon;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Validates that a datetime value is at least a configurable number of minutes
 * in the future relative to the current time in Asia/Manila (PHT, UTC+8).
 *
 * Naive datetime strings (no timezone offset) are interpreted as Asia/Manila
 * before comparison.
 */
class FutureDatetimeRule implements ValidationRule
{
    /**
     * @param int $minimumMinutes Minimum number of minutes the datetime must be in the future.
     */
    public function __construct(private int $minimumMinutes = 1)
    {
    }

    /**
     * Run the validation rule.
     *
     * @param  string   $attribute
     * @param  mixed    $value
     * @param  \Closure $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail("The cutoff must be a future datetime (at least {$this->minimumMinutes} minute from now).");
            return;
        }

        try {
            // If the value contains a timezone offset (+HH:MM, -HH:MM, or Z) parse it as-is;
            // otherwise treat it as an Asia/Manila local datetime.
            if ($this->hasTimezoneOffset($value)) {
                $parsed = Carbon::parse($value)->setTimezone('Asia/Manila');
            } else {
                $parsed = Carbon::parse($value, 'Asia/Manila');
            }
        } catch (\Exception) {
            $fail("The cutoff must be a future datetime (at least {$this->minimumMinutes} minute from now).");
            return;
        }

        $threshold = Carbon::now('Asia/Manila')->addMinutes($this->minimumMinutes);

        if ($parsed->lt($threshold)) {
            $fail("The cutoff must be a future datetime (at least {$this->minimumMinutes} minute from now).");
        }
    }

    /**
     * Determine whether the raw datetime string already carries a timezone offset.
     *
     * Matches suffixes like: Z, +08:00, -05:00, +0800, +08
     */
    private function hasTimezoneOffset(string $value): bool
    {
        return (bool) preg_match('/[Zz]$|[+-]\d{2}:?\d{0,2}$/', trim($value));
    }
}
