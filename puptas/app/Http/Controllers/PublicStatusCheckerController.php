<?php

namespace App\Http\Controllers;

use App\Http\Requests\CheckStatusRequest;
use App\Models\TestPasser;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class PublicStatusCheckerController extends Controller
{
    public function check(CheckStatusRequest $request): JsonResponse
    {
        $referenceNumber = $request->validated('referenceNumber');
        $firstName       = strtolower(trim($request->validated('firstName')));
        $lastName        = strtolower(trim($request->validated('lastName')));

        $passer = TestPasser::where('reference_number', $referenceNumber)
            ->whereRaw('LOWER(TRIM(first_name)) = ?', [$firstName])
            ->whereRaw('LOWER(TRIM(surname)) = ?', [$lastName])
            ->first();

        $matched = $passer !== null;

        Log::info('status_check_attempt', [
            'ip'               => $request->ip(),
            'reference_number' => $referenceNumber,
            'first_name_hash'  => hash('sha256', $firstName),
            'last_name_hash'   => hash('sha256', $lastName),
            'outcome'          => $matched ? 'matched' : 'not_matched',
        ]);

        if ($matched) {
            return response()->json([
                'qualified'        => true,
                'passer_status_id' => $passer->passer_status_id,
                'first_name'       => ucwords(strtolower(trim($passer->first_name))),
                'last_name'        => ucwords(strtolower(trim($passer->surname))),
                'full_name'        => ucwords(strtolower(trim($passer->first_name))) . ' ' . ucwords(strtolower(trim($passer->surname))),
                'reference_number' => $passer->reference_number,
                'batch_number'     => $passer->batch_number,
                'confirmation_url' => 'https://identity-provider.isaxbsit2027.com/register?client_id=037f48dd-245b-450b-9e7a-3348b65b9dad',
            ]);
        }

        // Format submitted name for display (title-case)
        $displayFirst = ucwords(strtolower(trim($request->validated('firstName'))));
        $displayLast  = ucwords(strtolower(trim($request->validated('lastName'))));

        return response()->json([
            'qualified'    => false,
            'first_name'   => $displayFirst,
            'last_name'    => $displayLast,
            'message'      => 'failed',
        ]);
    }
}
