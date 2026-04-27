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

                    $stored = $this->fileService->store($uploadedFile, 'uploads/files');

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

                    \App\Jobs\ProcessGradeOcr::dispatch($userFile->id);
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

        // Return all necessary user data + files
        return response()->json([
            'firstname' => $user->firstname,
            'middlename' => $user->middlename,
            'lastname' => $user->lastname,
            'birthday' => $user->birthday,
            'sex' => $user->sex,
            'contactnumber' => $user->contactnumber,
            'street_address' => $user->street_address,
            'barangay' => $user->barangay,
            'city' => $user->city,
            'province' => $user->province,
            'postal_code' => $user->postal_code,
            'email' => $user->email,
            'school' => $applicantProfile?->school,
            'schoolAdd' => $applicantProfile?->school_address,
            'schoolyear' => $user->schoolyear,
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

        $diskName = $this->resolveDiskForPath($file->file_path);
        $disk = Storage::disk($diskName);

        if (!$disk->exists($file->file_path)) {
            abort(Response::HTTP_NOT_FOUND);
        }

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

        return response($disk->get($file->file_path), Response::HTTP_OK, $headers);
    }

    private function resolveDiskForPath(string $path): string
    {
        return FileMapper::resolveDiskForPath($path);
    }
}
