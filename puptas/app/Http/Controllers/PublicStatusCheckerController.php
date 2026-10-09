<?php

namespace App\Http\Controllers;

use App\Helpers\DataMaskingHelper;
use App\Http\Requests\CheckStatusRequest;
use App\Models\TestPasser;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class PublicStatusCheckerController extends Controller
{
    public function __construct(
        private AuditLogService $auditLogService
    ) {}

    public function check(CheckStatusRequest $request): JsonResponse
    {
        $referenceNumber = (string) $request->validated('referenceNumber');
        $rawFirstName    = (string) $request->validated('firstName');
        $rawLastName     = (string) $request->validated('lastName');

        $firstName = strtolower(trim($rawFirstName));
        $lastName  = strtolower(trim($rawLastName));

        // Name normalization: trim, Str::ascii, lowercase, collapse internal whitespace
        $normalize = function (string $name): string {
            $ascii = Str::ascii($name);
            $lowered = strtolower(trim($ascii));
            return (string) preg_replace('/\s+/', ' ', $lowered);
        };

        $normFirst = $normalize($rawFirstName);
        $normLast  = $normalize($rawLastName);
        $nameHash  = hash('sha256', $normFirst . ' ' . $normLast);
        $nameKey   = 'status_checker:fail:name:' . $nameHash;
        $ipKey     = 'status_checker:fail:ip:' . $request->ip();

        $nameFailLimit = (int) config('services.status_checker.name_fail_limit', 30);
        $nameFailDecay = (int) config('services.status_checker.name_fail_decay', 3600);
        $ipFailLimit   = (int) config('services.status_checker.ip_fail_limit', 100);
        $ipFailDecay   = (int) config('services.status_checker.ip_fail_decay', 600);

        // Mirror existing production guard and disable-throttling flag
        $isThrottlingDisabled = !app()->isProduction() && config('services.status_checker.disable_throttling', false);

        // Check failure limits BEFORE querying the database
        if (!$isThrottlingDisabled) {
            $nameBlocked = RateLimiter::tooManyAttempts($nameKey, $nameFailLimit);
            $ipBlocked   = RateLimiter::tooManyAttempts($ipKey, $ipFailLimit);

            if ($nameBlocked || $ipBlocked) {
                $retryAfter = max(
                    $nameBlocked ? RateLimiter::availableIn($nameKey) : 0,
                    $ipBlocked   ? RateLimiter::availableIn($ipKey)   : 0,
                    1
                );

                return response()->json([
                    'found'       => false,
                    'message'     => 'too_many_attempts',
                    'retry_after' => $retryAfter,
                ], 429, [
                    'Retry-After' => (string) $retryAfter,
                ]);
            }
        }

        $passer = TestPasser::where('reference_number', $referenceNumber)
            ->whereRaw('LOWER(TRIM(first_name)) = ?', [$firstName])
            ->whereRaw('LOWER(TRIM(surname)) = ?', [$lastName])
            ->first();

        $matched = $passer !== null;

        // Count FAILED lookups only. Successes do not consume or reset counters.
        if (!$matched && !$isThrottlingDisabled) {
            RateLimiter::hit($nameKey, $nameFailDecay);
            RateLimiter::hit($ipKey, $ipFailDecay);
        }

        // Log to application log with masked reference number
        Log::info('status_check_attempt', [
            'ip'               => $request->ip(),
            'reference_number' => DataMaskingHelper::maskReferenceForLog($referenceNumber),
            'first_name_hash'  => hash('sha256', $firstName),
            'last_name_hash'   => hash('sha256', $lastName),
            'outcome'          => $matched ? 'matched' : 'not_matched',
        ]);

        // Log to audit trail
        $this->auditLogService->logStatusCheck(
            $referenceNumber,
            $firstName,
            $lastName,
            $matched,
            $request->ip()
        );

        if ($matched) {
            $statusId = $passer->passer_status_id;
            $statusLabels = [1 => 'qualified', 2 => 'waitlisted', 3 => 'not_qualified', 4 => 'waitlisted_below_cutoff'];
            $statusLabel = $statusLabels[$statusId] ?? 'pending';

            return response()->json([
                'found'                    => true,
                'qualified'                => $statusId === 1,
                'waitlisted'               => $statusId === 2,
                'not_qualified'            => $statusId === 3,
                'waitlisted_below_cutoff'  => $statusId === 4,
                'status'                   => $statusLabel,
                'passer_status_id'         => $statusId,
                'first_name'               => ucwords(strtolower(trim($passer->first_name))),
                'last_name'                => ucwords(strtolower(trim($passer->surname))),
                'full_name'                => ucwords(strtolower(trim($passer->first_name))) . ' ' . ucwords(strtolower(trim($passer->surname))),
                'reference_number'         => $passer->reference_number,
                'batch_number'             => $statusId === 4 ? null : $passer->batch_number,
                'confirmation_url'         => 'https://identity-provider.isaxbsit2027.com/register?client_id=037f48dd-245b-450b-9e7a-3348b65b9dad',
            ]);
        }

        // Format submitted name for display (title-case)
        $displayFirst = ucwords(strtolower(trim($request->validated('firstName'))));
        $displayLast  = ucwords(strtolower(trim($request->validated('lastName'))));

        return response()->json([
            'found'        => false,
            'qualified'    => false,
            'first_name'   => $displayFirst,
            'last_name'    => $displayLast,
            'message'      => 'no_record',
        ]);
    }
}
