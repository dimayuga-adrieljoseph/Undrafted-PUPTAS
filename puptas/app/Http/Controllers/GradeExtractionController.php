<?php

namespace App\Http\Controllers;

use App\Services\DoclingParser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class GradeExtractionController extends Controller
{
    public function __construct(
        private DoclingParser $doclingParser
    ) {}

    /**
     * Extract grades from uploaded images and store the result in the session,
     * then return a JSON response with the grade page URL so the frontend can
     * navigate there via Inertia. The grade page controllers read the extraction
     * result from the session and pass it as an Inertia prop.
     */
    public function extract(Request $request): JsonResponse
    {
        $user = $request->user();

        // Docling extraction has been disabled for performance reasons.
        // We now directly redirect the user to the grade input form 
        // to manually enter their grades.
        return response()->json([
            'redirect' => $this->getStrandGradeUrl($user),
            'fallback' => true, // Keep fallback true so frontend knows it's manual mode if it relies on it
        ]);
    }

    private function getStrandGradeUrl($user): string
    {
        $profile = \App\Models\ApplicantProfile::where('user_id', (string) $user->id)->first();
        $strand = strtoupper($profile?->strand ?? 'ABM');

        return match ($strand) {
            'ICT'   => '/grades/ict',
            'HUMSS' => '/grades/humss',
            'GAS'   => '/grades/gas',
            'STEM'  => '/grades/stem',
            'TVL'   => '/grades/tvl',
            default => '/grades/abm',
        };
    }
}
