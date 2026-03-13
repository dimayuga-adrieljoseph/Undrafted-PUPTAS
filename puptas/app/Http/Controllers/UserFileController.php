<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\UserFile;
use App\Helpers\FileMapper;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Storage;
use App\Rules\ValidationRules;

class UserFileController extends Controller
{
    private const STAFF_ROLE_IDS = [2, 3, 4, 5, 6, 7];

    public function uploadFiles(Request $request)
    {
        $request->validate(ValidationRules::userFileUpload());

        $user = User::where('email', $request->email)->firstOrFail();

        $filesToSave = [
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
                $uploadedFile = $request->file($inputName);
                $path = $uploadedFile->store('uploads/files', 'public');

                UserFile::updateOrCreate(
                    [
                        'user_id' => $user->id,
                        'type' => $type,
                    ],
                    [
                        'file_path' => $path,
                        'original_name' => $uploadedFile->getClientOriginalName(),
                        'application_id' => $request->application_id ?? null,
                        'status' => 'pending',
                    ]
                );
            }
        }

        return response()->json(['message' => 'Files uploaded successfully']);
    }

    public function getUserApplication()
    {
        $user = auth()->user();

        $files = $user->files()->get();

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
            'school' => $user->school,
            'schoolAdd' => $user->schoolAdd,
            'schoolyear' => $user->schoolyear,
            'dateGrad' => $user->dateGrad,
            'strand' => $user->strand,
            'track' => $user->track,
            'uploadedFiles' => $uploadedFiles,
        ]);
    }

    public function preview(Request $request, UserFile $file)
    {
        if (!$request->hasValidSignature()) {
            abort(Response::HTTP_FORBIDDEN);
        }

        $authUser = $request->user();
        $isOwner = $authUser && (int) $authUser->id === (int) $file->user_id;
        $isStaff = $authUser && in_array((int) $authUser->role_id, self::STAFF_ROLE_IDS, true);

        if (!$isOwner && !$isStaff) {
            abort(Response::HTTP_FORBIDDEN);
        }

        $diskName = $this->resolveDiskForPath($file->file_path);
        $disk = Storage::disk($diskName);

        if (!$disk->exists($file->file_path)) {
            abort(Response::HTTP_NOT_FOUND);
        }

        $mimeType = $this->guessMimeType($file->original_name, $file->file_path);

        if ($diskName === 'public' || $diskName === 'local') {
            return response()->file($disk->path($file->file_path), [
                'Content-Type' => $mimeType,
                'Content-Disposition' => 'inline; filename="' . ($file->original_name ?? 'document') . '"',
                'Cache-Control' => 'private, no-store, no-cache, must-revalidate, max-age=0',
                'Pragma' => 'no-cache',
                'Expires' => '0',
            ]);
        }

        return response($disk->get($file->file_path), Response::HTTP_OK, [
            'Content-Type' => $mimeType,
            'Content-Disposition' => 'inline; filename="' . ($file->original_name ?? 'document') . '"',
            'Cache-Control' => 'private, no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }

    private function resolveDiskForPath(string $path): string
    {
        $configuredDefault = config('filesystems.default', 'public');
        $candidateDisks = array_unique([$configuredDefault, 'public', 'local', 's3']);

        foreach ($candidateDisks as $diskName) {
            try {
                if (Storage::disk($diskName)->exists($path)) {
                    return $diskName;
                }
            } catch (\Throwable $e) {
                continue;
            }
        }

        return 'public';
    }

    private function guessMimeType(?string $originalName, string $path): string
    {
        $extension = strtolower(pathinfo($originalName ?: $path, PATHINFO_EXTENSION));

        return match ($extension) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'pdf' => 'application/pdf',
            default => 'application/octet-stream',
        };
    }
}
