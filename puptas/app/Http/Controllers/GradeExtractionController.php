<?php

namespace App\Http\Controllers;

use App\Exceptions\OpenRouterApiException;
use App\Services\GradeExtractionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class GradeExtractionController extends Controller
{
    public function __construct(
        private GradeExtractionService $gradeExtractionService
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

        try {
            $result = $this->gradeExtractionService->extract($user);

            // Store extraction result in session so the grade input page can
            // receive it as a proper Inertia prop on the next GET request.
            $request->session()->put('extraction_result', $result);

            return response()->json(['redirect' => $this->getStrandGradeUrl($user)]);
        } catch (\InvalidArgumentException $e) {
            Log::warning('Grade extraction: no valid image files', [
                'user_id'    => $user?->id,
                'message'    => $e->getMessage(),
                'file_count' => \App\Models\UserFile::where('user_id', $user?->id)->count(),
            ]);
            return response()->json(['error' => $e->getMessage()], 422);
        } catch (OpenRouterApiException $e) {
            Log::error('OpenRouter API error during grade extraction', [
                'user_id'       => $user?->id,
                'message'       => $e->getMessage(),
                'status_code'   => $e->getStatusCode(),
                'response_body' => $e->getResponseBody(),
            ]);

            return response()->json(['error' => 'OpenRouter API is currently unavailable. Please try again later.'], 503);
        } catch (\RuntimeException $e) {
            Log::error('Grade extraction failed', [
                'user_id' => $user?->id,
                'message' => $e->getMessage(),
                'payload' => $e->getMessage(),
            ]);

            return response()->json(['error' => $e->getMessage()], 422);
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
