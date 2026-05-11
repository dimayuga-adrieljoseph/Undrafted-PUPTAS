<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserFile;
use App\Helpers\FileMapper;
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
    private const STAFF_ROLE_IDS = [2, 3, 4, 5, 6, 7];

    /**
     * Load a single document file URL
     * 
     * @param int $userId
     * @param string $fileType
     * @return JsonResponse
     */
    public function loadDocument(int $userId, string $fileType): JsonResponse
    {
        // Verify staff access
        if (!in_array(auth()->user()->role_id, self::STAFF_ROLE_IDS)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Find the file
        $file = UserFile::where('user_id', $userId)
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

        // Build preview URL
        $url = FileMapper::buildPreviewUrl($file->file_path);
        $mimeType = FileMapper::detectMimeType($file->file_path);
        $isImage = str_starts_with($mimeType, 'image/');

        return response()->json([
            'fileType' => $fileType,
            'url' => $url,
            'status' => $file->status ?? 'pending',
            'comment' => $file->comment,
            'isImage' => $isImage,
            'originalName' => $file->original_name,
        ]);
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
        // Verify staff access
        if (!in_array(auth()->user()->role_id, self::STAFF_ROLE_IDS)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $fileTypes = $request->input('fileTypes', []);
        
        if (empty($fileTypes) || !is_array($fileTypes)) {
            return response()->json(['message' => 'Invalid file types'], 400);
        }

        // Limit batch size to prevent abuse
        if (count($fileTypes) > 20) {
            return response()->json(['message' => 'Too many files requested'], 400);
        }

        // Fetch files
        $files = UserFile::where('user_id', $userId)
            ->whereIn('type', $fileTypes)
            ->get()
            ->keyBy('type');

        $result = [];

        foreach ($fileTypes as $fileType) {
            if ($files->has($fileType)) {
                $file = $files->get($fileType);
                $url = FileMapper::buildPreviewUrl($file->file_path);
                $mimeType = FileMapper::detectMimeType($file->file_path);
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
        }

        return response()->json([
            'files' => $result,
        ]);
    }

    /**
     * Load applicant grades separately
     * 
     * @param int $userId
     * @return JsonResponse
     */
    public function loadGrades(int $userId): JsonResponse
    {
        // Verify staff access
        if (!in_array(auth()->user()->role_id, self::STAFF_ROLE_IDS)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $user = User::with('grades')->find($userId);

        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        return response()->json([
            'grades' => $user->grades,
        ]);
    }
}
