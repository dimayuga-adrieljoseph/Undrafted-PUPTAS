<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Services\ConfirmationService;
use App\Services\FileService;
use App\Services\AuditLogService;
use App\Http\Requests\SubmitApplicationRequest;
use App\Http\Requests\ReuploadFileRequest;
use App\Helpers\FileMapper;

class ConfirmationController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @param ConfirmationService $confirmationService
     */
    public function __construct(
        private ConfirmationService $confirmationService,
        private AuditLogService $auditLogService,
        private FileService $fileService
    ) {}

    /**
     * Show confirmation data for the authenticated user
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function show()
    {
        $user = Auth::user();
        $data = $this->confirmationService->getConfirmationData($user);

        return response()->json($data);
    }

    /**
     * Submit an application
     *
     * @param SubmitApplicationRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function submit(SubmitApplicationRequest $request)
    {
        $user = Auth::user();

        \Log::info('📥 Incoming submit data', $request->validated());

        try {
            $application = $this->confirmationService->submitApplication(
                $user,
                $request->validated()
            );

            $this->auditLogService->logActivity('CREATE', 'Applications', "Applicant {$user->firstname} {$user->lastname} submitted application #{$application->id}.", $user, 'ADMISSION_DATA');

            return response()->json([
                'message' => 'Application submitted.',
                'status' => $application->status,
                'submitted_at' => $application->submitted_at,
            ]);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            \Log::error('Application submission failed', [
                'user_id' => $user->id,
                'status_code' => $e->getStatusCode(),
                'exception_class' => get_class($e),
            ]);

            // Return generic message for client, actual message logged for debugging
            $safeMessages = [
                409 => 'Application has already been submitted.',
                422 => 'Unable to submit application. Please ensure all requirements are met.',
            ];

            return response()->json([
                'message' => $safeMessages[$e->getStatusCode()] ?? 'An error occurred while submitting your application.'
            ], $e->getStatusCode());
        }
    }

    /**
     * Reupload a file
     *
     * @param ReuploadFileRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function reupload(ReuploadFileRequest $request)
    {
        $user = auth()->user();
        $inputName = $request->input('field');

        try {
            $result = $this->confirmationService->reuploadFile(
                $user,
                $inputName,
                $request->file('file')
            );

            $this->auditLogService->logActivity('UPDATE', 'Applications', "Applicant {$user->firstname} {$user->lastname} uploaded document '{$inputName}'.", $user, 'ADMISSION_DATA');

            return response()->json($result);
        } catch (\InvalidArgumentException $e) {
            \Log::warning('File reupload failed — invalid field', [
                'user_id'         => $user->id,
                'field'           => $inputName,
                'exception_class' => get_class($e),
                'message'         => $e->getMessage(),
            ]);

            return response()->json(['message' => 'Invalid file field specified.'], 400);
        } catch (\Throwable $e) {
            // Catch-all: covers RuntimeException, TypeError, Error, etc.
            // Nothing leaks as a raw Laravel 500 — full details go to the log only.
            \Log::error('File reupload failed', [
                'user_id'         => $user->id,
                'field'           => $inputName,
                'exception_class' => get_class($e),
                'message'         => $e->getMessage(),
                'file'            => $e->getFile(),
                'line'            => $e->getLine(),
            ]);

            $isConnectivity = str_contains($e->getMessage(), 'S3')
                || str_contains($e->getMessage(), 'connect')
                || str_contains($e->getMessage(), 'timeout')
                || str_contains($e->getMessage(), 'cURL')
                || str_contains($e->getMessage(), 'Could not resolve host');

            return response()->json([
                'message' => $isConnectivity
                    ? 'Storage service temporarily unavailable. Please try again.'
                    : 'File upload failed. Please try again.',
            ], $isConnectivity ? 503 : 500);
        }
    }


    /**
     * Get eligible programs for the authenticated user
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getEligiblePrograms()
    {
        $user = Auth::user();
        $result = $this->confirmationService->getEligiblePrograms($user);

        return response()->json($result);
    }

    /**
     * Check the upload status of a specific file field.
     * Used by the frontend to replace localStorage-based "in progress" guards
     * with authoritative backend state.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function fileStatus(Request $request)
    {
        $request->validate([
            'field' => ['required', 'string', 'in:' . FileMapper::getValidFileFields()],
        ]);

        $user = auth()->user();
        $type = FileMapper::MAPPING[$request->input('field')] ?? null;

        if (!$type) {
            return response()->json(['status' => null]);
        }

        $file = \App\Models\UserFile::where('user_id', (string) $user->id)
            ->where('type', $type)
            ->first();

        if (!$file) {
            return response()->json(['status' => null, 'hasFile' => false]);
        }

        return response()->json([
            'status' => $file->status,
            'hasFile' => $file->isUploaded(),
            'isUploading' => $file->isUploading(),
            'isFailed' => $file->isFailed(),
            'updatedAt' => $file->updated_at?->toIso8601String(),
        ]);
    }

    /**
     * Generate a presigned URL for direct-to-S3 upload.
     * This allows the client to upload directly to storage without proxying through the server.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getUploadUrl(Request $request)
    {
        $allowedExtensions = app()->environment('local')
            ? 'jpg,jpeg,png,webp,gif,pdf,doc,docx,txt'
            : 'jpg,jpeg,png,webp,gif,pdf';

        $request->validate([
            'field' => ['required', 'string', 'in:' . FileMapper::getValidFileFields()],
            'extension' => ['required', 'string', 'in:' . $allowedExtensions],
            'content_type' => ['required', 'string'],
        ]);

        $user = auth()->user();
        $field = $request->input('field');
        $type = FileMapper::MAPPING[$field] ?? null;

        try {
            $result = $this->fileService->generateUploadUrl(
                'uploads/files',
                $request->input('extension')
            );

            // Mark the file as 'uploading' in the DB so the backend is authoritative
            // about the in-progress state (even for direct S3 uploads).
            if ($type) {
                \App\Models\UserFile::updateOrCreate(
                    ['user_id' => $user->id, 'type' => $type],
                    ['status' => 'uploading', 'original_name' => 'uploading...']
                );
            }

            return response()->json($result);
        } catch (\Throwable $e) {
            \Log::error('Failed to generate upload URL', [
                'user_id' => $user->id,
                'field' => $field,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Unable to prepare upload. Please try again.',
            ], 500);
        }
    }

    /**
     * Resubmit a returned application back to the evaluator stage.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function resubmit()
    {
        $user = Auth::user();

        try {
            $application = $this->confirmationService->resubmitApplication($user);

            $this->auditLogService->logActivity('UPDATE', 'Applications', "Applicant {$user->firstname} {$user->lastname} resubmitted application #{$application->id} for evaluation.", $user, 'ADMISSION_DATA');

            return response()->json([
                'message' => 'Application resubmitted for evaluation.',
                'status' => $application->status,
            ]);
        } catch (\Throwable $e) {
            \Log::error('Application resubmit failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Confirm a direct-to-S3 upload by recording the file in the database.
     * Called after the client successfully uploads directly to S3.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function confirmUpload(Request $request)
    {
        $request->validate([
            'field' => ['required', 'string', 'in:' . FileMapper::getValidFileFields()],
            'path' => ['required', 'string'],
            'original_name' => ['required', 'string', 'max:255'],
        ]);

        $user = auth()->user();
        $field = $request->input('field');
        $path = $request->input('path');
        $originalName = $request->input('original_name');

        try {
            $result = $this->confirmationService->confirmDirectUpload(
                $user,
                $field,
                $path,
                $originalName
            );

            $this->auditLogService->logActivity(
                'UPDATE',
                'Applications',
                "Applicant {$user->firstname} {$user->lastname} uploaded document '{$field}' (direct).",
                $user,
                'ADMISSION_DATA'
            );

            return response()->json($result);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => 'Invalid file field specified.'], 400);
        } catch (\Throwable $e) {
            \Log::error('Confirm upload failed', [
                'user_id' => $user->id,
                'field' => $field,
                'path' => $path,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Failed to confirm upload. Please try again.',
            ], 500);
        }
    }
}
