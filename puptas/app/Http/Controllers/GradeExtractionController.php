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

        $hasDoclingFiles = \App\Models\UserFile::where('user_id', $user->id)
            ->whereNotNull('docling_json')
            ->exists();

        if (!$hasDoclingFiles) {
            \Log::warning('GradeExtractionController: No UserFile records with docling_json found for user ' . $user->id);
            return response()->json([
                'redirect' => $this->getStrandGradeUrl($user),
                'fallback' => true,
            ]);
        }

        try {
            $result = $this->doclingParser->extract($user);

            // Store extraction result in session so the grade input page can
            // receive it as a proper Inertia prop on the next GET request.
            $request->session()->put('extraction_result', $result);

            return response()->json(['redirect' => $this->getStrandGradeUrl($user)]);
        } catch (\InvalidArgumentException $e) {
            Log::warning('Grade extraction: no valid image files, falling back to manual input', [
                'user_id'    => $user?->id,
                'message'    => $e->getMessage(),
                'file_count' => \App\Models\UserFile::where('user_id', $user?->id)->count(),
            ]);
            return response()->json([
                'redirect'      => $this->getStrandGradeUrl($user),
                'fallback'      => true,
                'fallback_reason' => $e->getMessage(),
            ]);
        } catch (\RuntimeException $e) {
            Log::error('Grade extraction failed, falling back to manual input', [
                'user_id' => $user?->id,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'redirect'        => $this->getStrandGradeUrl($user),
                'fallback'        => true,
                'fallback_reason' => $e->getMessage(),
            ]);
        }
    }

    private function getStrandGradeUrl($user): string
    {
        $profile = \App\Models\ApplicantProfile::where('user_id', $user->id)->first();
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
