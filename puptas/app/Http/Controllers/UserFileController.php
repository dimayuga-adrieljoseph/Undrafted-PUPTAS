<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\UserFile;
use Illuminate\Support\Facades\Storage;
use App\Rules\ValidationRules;

class UserFileController extends Controller
{
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
                    $uploadedFiles['schoolId'] = Storage::url($file->file_path);
                    break;
                case 'non_enroll_cert':
                    $uploadedFiles['nonEnrollCert'] = Storage::url($file->file_path);
                    break;
                case 'psa':
                    $uploadedFiles['psa'] = Storage::url($file->file_path);
                    break;
                case 'good_moral':
                    $uploadedFiles['goodMoral'] = Storage::url($file->file_path);
                    break;
                case 'under_oath':
                    $uploadedFiles['underOath'] = Storage::url($file->file_path);
                    break;
                case 'photo_2x2':
                    $uploadedFiles['photo2x2'] = Storage::url($file->file_path);
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
            'address' => $user->address,
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
}
