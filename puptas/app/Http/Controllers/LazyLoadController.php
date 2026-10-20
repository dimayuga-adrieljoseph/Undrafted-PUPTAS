<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserFile;
use App\Helpers\FileMapper;
use App\Http\Traits\ManagesApplicationFiles;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

/**
 * LazyLoadController
 * 
 * Handles lazy loading of applicant documents and heavy resources
 * to improve initial page load performance for staff portals.
 */
class LazyLoadController extends Controller
{
    private const STAFF_ROLE_IDS = [2, 3, 4, 5, 6, 7, 8];

    /**
     * Enforce program scoping for role 3 (Document Evaluator) only.
     * Roles 2, 4 (Interviewer), 7 (SuperAdmin), 8 (Grade Evaluator), 5 (Medical), 6 (Registrar) are exempt.
     * If the applicant has several applications, access is allowed if ANY program is in the user's assigned programs.
     */
    private function checkProgramScoping(int $userId): ?JsonResponse
    {
        $user = auth()->user();
        if (!$user || $user->role_id !== \App\Enums\RoleId::DocumentEvaluator->value) {
            return null;
        }

        $assignedProgramIds = $user->programs()->pluck('programs.id')->toArray();
        $applicantProgramIds = \App\Models\Application::where('user_id', (string) $userId)
            ->whereNotNull('program_id')
            ->pluck('program_id')
            ->toArray();

        if (empty(array_intersect($applicantProgramIds, $assignedProgramIds))) {
            return response()->json([
                'message' => 'You are not authorized to evaluate applicants for this program.',
            ], 403);
        }

        return null;
    }

    /**
     * Load a single document file URL
     * 
     * @param int $userId
     * @param string $fileType
     * @return JsonResponse
     */
    public function loadDocument(int $userId, string $fileType): JsonResponse
    {
        try {
            // Verify staff access
            if (!auth()->check() || !in_array(auth()->user()->role_id, self::STAFF_ROLE_IDS)) {
                return response()->json(['message' => 'Unauthorized access'], 403);
            }

            if ($scopingError = $this->checkProgramScoping($userId)) {
                return $scopingError;
            }

            if ($stageError = ManagesApplicationFiles::checkInterviewerStage($userId)) {
                return $stageError;
            }

            // Find the file
            $file = UserFile::where('user_id', (string) $userId)
                ->where('type', $fileType)
                ->first();

            if (!$file) {
                return response()->json([
                    'fileType' => $fileType,
                    'url' => null,
                    'status' => 'not_found',
                    'isImage' => false,
                ]);
            }

            // Build preview URL using the UserFile object
            $url = FileMapper::buildPreviewUrl($file);
            $mimeType = FileMapper::detectMimeType($file);
            $isImage = str_starts_with($mimeType, 'image/');

            return response()->json([
                'fileType' => $fileType,
                'url' => $url,
                'status' => $file->status ?? 'pending',
                'comment' => $file->comment,
                'isImage' => $isImage,
                'originalName' => $file->original_name,
            ]);
        } catch (\Exception $e) {
            \Log::error('Failed to load document', [
                'userId' => $userId,
                'fileType' => $fileType,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Failed to load document. Please try again.',
                'fileType' => $fileType,
                'url' => null,
                'status' => 'error',
            ], 500);
        }
    }

    /**
     * Load multiple documents in batch
     * 
     * @param int $userId
     * @param Request $request
     * @return JsonResponse
     */
    public function loadDocumentsBatch(int $userId, Request $request): JsonResponse
    {
        try {
            // Verify staff access
            if (!auth()->check() || !in_array(auth()->user()->role_id, self::STAFF_ROLE_IDS)) {
                return response()->json(['message' => 'Unauthorized access'], 403);
            }

            if ($scopingError = $this->checkProgramScoping($userId)) {
                return $scopingError;
            }

            if ($stageError = ManagesApplicationFiles::checkInterviewerStage($userId)) {
                return $stageError;
            }

            $fileTypes = $request->input('fileTypes', []);
            
            if (empty($fileTypes) || !is_array($fileTypes)) {
                return response()->json(['message' => 'Invalid file types provided'], 400);
            }

            // Limit batch size to prevent abuse
            if (count($fileTypes) > 20) {
                return response()->json(['message' => 'Too many files requested. Maximum 20 files per batch.'], 400);
            }

            // Fetch files
            $files = UserFile::where('user_id', (string) $userId)
                ->whereIn('type', $fileTypes)
                ->get()
                ->keyBy('type');

            $result = [];

            foreach ($fileTypes as $fileType) {
                try {
                    if ($files->has($fileType)) {
                        $file = $files->get($fileType);
                        // Use the UserFile object, not the file path
                        $url = FileMapper::buildPreviewUrl($file);
                        $mimeType = FileMapper::detectMimeType($file);
                        $isImage = str_starts_with($mimeType, 'image/');

                        $result[$fileType] = [
                            'url' => $url,
                            'status' => $file->status ?? 'pending',
                            'comment' => $file->comment,
                            'isImage' => $isImage,
                            'originalName' => $file->original_name,
                        ];
                    } else {
                        $result[$fileType] = [
                            'url' => null,
                            'status' => 'not_found',
                            'isImage' => false,
                            'originalName' => null,
                        ];
                    }
                } catch (\Exception $e) {
                    \Log::error('Failed to process file in batch', [
                        'userId' => $userId,
                        'fileType' => $fileType,
                        'error' => $e->getMessage(),
                    ]);

                    $result[$fileType] = [
                        'url' => null,
                        'status' => 'error',
                        'isImage' => false,
                        'originalName' => null,
                    ];
                }
            }

            return response()->json([
                'files' => $result,
            ]);
        } catch (\Exception $e) {
            \Log::error('Failed to load documents batch', [
                'userId' => $userId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Failed to load documents. Please try again.',
            ], 500);
        }
    }

    /**
     * Load applicant grades separately
     * 
     * @param int $userId
     * @return JsonResponse
     */
    public function loadGrades(int $userId): JsonResponse
    {
        try {
            // Verify staff access
            if (!auth()->check() || !in_array(auth()->user()->role_id, self::STAFF_ROLE_IDS)) {
                return response()->json(['message' => 'Unauthorized access'], 403);
            }

            if ($scopingError = $this->checkProgramScoping($userId)) {
                return $scopingError;
            }

            if ($stageError = ManagesApplicationFiles::checkInterviewerStage($userId)) {
                return $stageError;
            }

            $user = User::with('grades')->find($userId);

            if (!$user) {
                return response()->json(['message' => 'User not found'], 404);
            }

            return response()->json([
                'grades' => $user->grades,
            ]);
        } catch (\Exception $e) {
            \Log::error('Failed to load grades', [
                'userId' => $userId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Failed to load grades. Please try again.',
                'grades' => null,
            ], 500);
        }
    }
}
