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
     * Generate a presigned URL for direct-to-S3 upload.
     * This allows the client to upload directly to storage without proxying through the server.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getUploadUrl(Request $request)
    {
        $request->validate([
            'field' => ['required', 'string', 'in:' . FileMapper::getValidFileFields()],
            'extension' => ['required', 'string', 'in:jpg,jpeg,png,webp,gif,pdf'],
            'content_type' => ['required', 'string'],
        ]);

        try {
            $result = $this->fileService->generateUploadUrl(
                'uploads/files',
                $request->input('extension')
            );

            return response()->json($result);
        } catch (\Throwable $e) {
            \Log::error('Failed to generate upload URL', [
                'user_id' => auth()->id(),
                'field' => $request->input('field'),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Unable to prepare upload. Please try again.',
            ], 500);
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
