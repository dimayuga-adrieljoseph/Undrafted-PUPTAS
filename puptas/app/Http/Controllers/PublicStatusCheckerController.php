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
        $normalizedEmail = strtolower(trim($request->validated('email')));
        $referenceNumber = $request->validated('referenceNumber');

        $passer = TestPasser::where('reference_number', $referenceNumber)
            ->where('email', $normalizedEmail)
            ->first();

        $matched = $passer !== null;

        Log::info('status_check_attempt', [
            'ip'               => $request->ip(),
            'reference_number' => $referenceNumber,
            'email_hash'       => hash('sha256', $normalizedEmail),
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
