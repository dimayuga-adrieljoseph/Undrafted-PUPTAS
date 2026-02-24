<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use App\Services\ConfirmationService;
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
        private ConfirmationService $confirmationService
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

            return response()->json([
                'message' => 'Application submitted.',
                'status' => $application->status,
                'submitted_at' => $application->submitted_at,
            ]);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            return response()->json(['message' => $e->getMessage()], $e->getStatusCode());
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

            return response()->json($result);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 400);
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
