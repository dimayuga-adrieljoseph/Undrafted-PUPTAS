<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\UserFile;
use App\Helpers\FileMapper;
use App\Services\ImageCompressionService;
use App\Services\FileService;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use App\Rules\ValidationRules;

class UserFileController extends Controller
{
    private const STAFF_ROLE_IDS = [2, 3, 4, 5, 6, 7];

    /**
     * @var ImageCompressionService
     */
    protected ImageCompressionService $compressionService;

    /**
     * @var FileService
     */
    protected FileService $fileService;

    /**
     * Create a new controller instance.
     */
    public function __construct(ImageCompressionService $compressionService, FileService $fileService)
    {
        $this->compressionService = $compressionService;
        $this->fileService = $fileService;
    }

    public function uploadFiles(Request $request)
    {
        $request->validate(ValidationRules::userFileUpload());

        $user = $request->user();

        abort_unless($user, Response::HTTP_UNAUTHORIZED);

        $filesToSave = [
            'file10Front' => 'file10_front',
            'file10' => 'file10_back',
            'file11' => 'file11_back',
            'file12' => 'file12_back',
            'file11Front' => 'file11_front',
            'file12Front' => 'file12_front',
            'fileId' => 'school_id',
            'fileNonEnroll' => 'non_enroll_cert',
            'filePSA' => 'psa',
            'fileGoodMoral' => 'good_moral',
            'fileUnderOath' => 'under_oath',
            'filePhoto2x2' => 'photo_2x2',
        ];

        foreach ($filesToSave as $inputName => $type) {
            if ($request->hasFile($inputName)) {
                try {
                    $uploadedFile = $request->file($inputName);

                    $stored = $this->fileService->storeRaw($uploadedFile, 'uploads/files');

                    $userFile = UserFile::updateOrCreate(
                        [
                            'user_id' => $user->id,
                            'type' => $type,
                        ],
                        [
                            'file_path' => $stored['path'],
                            'original_name' => $stored['original_name'],
                            'application_id' => $request->application_id ?? null,
                            'status' => 'pending',
                            'docling_json' => null,
                        ]
                    );

                    // OCR processing removed – no background job dispatched
                } catch (\InvalidArgumentException $e) {
                    return response()->json([
                        'message' => 'Image processing failed: ' . $e->getMessage(),
                    ], Response::HTTP_UNPROCESSABLE_ENTITY);
                } catch (\RuntimeException $e) {
                    $disk = config('filesystems.default', 'public');
                    $isConnectivityError = str_contains($e->getMessage(), 'S3') ||
                        str_contains($e->getMessage(), 'connect') ||
                        str_contains($e->getMessage(), 'Connection') ||
                        str_contains($e->getMessage(), 'timeout') ||
                        str_contains($e->getMessage(), 'unreachable') ||
                        str_contains($e->getMessage(), 'Could not resolve host') ||
                        str_contains($e->getMessage(), 'cURL');

                    Log::error('FileService store() failed', [
                        'user_id'           => $user->id,
                        'file_type'         => $type,
                        'disk'              => $disk,
                        'exception_message' => $e->getMessage(),
                    ]);

                    if ($isConnectivityError) {
                        return response()->json([
                            'message' => 'Storage service temporarily unavailable.',
                        ], Response::HTTP_SERVICE_UNAVAILABLE);
                    }

                    return response()->json([
                        'message' => 'File operation failed. Please try again.',
                    ], Response::HTTP_INTERNAL_SERVER_ERROR);
                } catch (\Exception $e) {
                    $disk = config('filesystems.default', 'public');

                    Log::error('FileService store() failed', [
                        'user_id'           => $user->id,
                        'file_type'         => $type,
                        'disk'              => $disk,
                        'exception_message' => $e->getMessage(),
                    ]);

                    return response()->json([
                        'message' => 'File operation failed. Please try again.',
                    ], Response::HTTP_INTERNAL_SERVER_ERROR);
                }
            }
        }

        return response()->json([
            'message' => 'Files uploaded successfully',
            'uploadedFiles' => FileMapper::formatFiles(UserFile::where('user_id', $user->id)->get()->keyBy('type')),
        ]);
    }

    public function getUserApplication()
    {
        $user = auth()->user();
        $user->load('applicantProfile');

        $files = UserFile::where('user_id', $user->id)->get();

        // Map files by type and generate URL
        $uploadedFiles = [];

        foreach ($files as $file) {
            // Example file type mapping, adjust according to your actual type names
            switch ($file->type) {
                case 'school_id':
                    $uploadedFiles['schoolId'] = FileMapper::buildPreviewUrl($file);
                    break;
                case 'non_enroll_cert':
                    $uploadedFiles['nonEnrollCert'] = FileMapper::buildPreviewUrl($file);
                    break;
                case 'psa':
                    $uploadedFiles['psa'] = FileMapper::buildPreviewUrl($file);
                    break;
                case 'good_moral':
                    $uploadedFiles['goodMoral'] = FileMapper::buildPreviewUrl($file);
                    break;
                case 'under_oath':
                    $uploadedFiles['underOath'] = FileMapper::buildPreviewUrl($file);
                    break;
                case 'photo_2x2':
                    $uploadedFiles['photo2x2'] = FileMapper::buildPreviewUrl($file);
                    break;
            }
        }

        $applicantProfile = $user->applicantProfile;
        
        // Load graduate type to derive schoolyear (same as ConfirmationService)
        $applicantProfile?->load('graduateTypes');
        $graduateType = $applicantProfile?->graduateTypes->first()?->label ?? null;

        // Return all necessary user data + files
        return response()->json([
            'firstname' => $user->firstname,
            'middlename' => $user->middlename,
            'lastname' => $user->lastname,
            'sex' => $user->sex,
            'contactnumber' => $user->contactnumber,
            'email' => $user->email,
            'schoolyear' => $graduateType,
            'dateGrad' => $applicantProfile?->date_graduated?->format('Y-m-d'),
            'strand' => $applicantProfile?->strand,
            'track' => $applicantProfile?->track,
            'uploadedFiles' => $uploadedFiles,
        ]);
    }

    public function preview(Request $request, UserFile $file)
    {
        if (!$request->hasValidSignature()) {
            abort(Response::HTTP_FORBIDDEN);
        }

        $authUser = $request->user();
        $isOwner = $authUser && (string) $authUser->id === (string) $file->user_id;
        $isStaff = $authUser && in_array((int) $authUser->role_id, self::STAFF_ROLE_IDS, true);

        if (!$isOwner && !$isStaff) {
            abort(Response::HTTP_FORBIDDEN);
        }

        // resolveDiskForPath() already calls exists() on each candidate disk to find the
        // correct one, so we can trust its result — a second exists() call would double the
        // S3 round-trips and create a second failure point.
        // If no disk reports the file as present, $found stays false and we 404.
        $found = false;
        $diskName = FileMapper::resolveDiskForPath($file->file_path, $found);
        if (!$found) {
            abort(Response::HTTP_NOT_FOUND);
        }
        $disk = Storage::disk($diskName);

        $mimeType = FileMapper::detectMimeType($file);
        $filename = FileMapper::sanitizeFilename($file->original_name);
        $contentDisposition = HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_INLINE, $filename);
        $headers = [
            'Content-Type' => $mimeType,
            'Content-Disposition' => $contentDisposition,
            'Cache-Control' => 'private, no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'Expires' => '0',
            'X-Content-Type-Options' => 'nosniff',
        ];

        if ($diskName === 'public' || $diskName === 'local') {
            return response()->file($disk->path($file->file_path), $headers);
        }

        // Generate a short-lived presigned S3 URL and redirect to it.
        // S3 serves the file directly to the browser — PHP memory is not involved.
        // Content-Type and Content-Disposition are forwarded as S3 response-override
        // parameters so inline preview behaviour is preserved without proxying the body.
        $temporaryUrl = $disk->temporaryUrl(
            $file->file_path,
            now()->addMinutes(5),
            [
                'ResponseContentType'        => $mimeType,
                'ResponseContentDisposition' => $contentDisposition,
            ]
        );

        return redirect($temporaryUrl);
    }
}
