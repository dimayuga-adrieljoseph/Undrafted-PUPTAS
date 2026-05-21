<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use App\Services\ConfirmationService;
use App\Services\AuditLogService;
use App\Http\Requests\SubmitApplicationRequest;
use App\Http\Requests\ReuploadFileRequest;

class ConfirmationController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @param ConfirmationService $confirmationService
     */
    public function __construct(
        private ConfirmationService $confirmationService,
        private AuditLogService $auditLogService
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
}
