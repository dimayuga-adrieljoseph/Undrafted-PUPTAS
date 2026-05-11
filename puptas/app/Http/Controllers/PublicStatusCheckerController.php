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
                'qualified'    => true,
                'batch_number' => $passer->batch_number,
                'message'      => 'Congratulations! You have passed the entrance exam.',
            ]);
        }

        return response()->json([
            'qualified' => false,
            'message'   => 'No matching record found. Please verify your details.',
        ]);
    }
}
