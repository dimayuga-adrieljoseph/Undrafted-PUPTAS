<?php

use App\Models\UserFile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use App\Auth\IdpUser;

function fakePngUpload($filename = 'image.png')
{
    return UploadedFile::fake()->image($filename, 10, 10);
}

test('guests cannot upload applicant files', function () {
    Storage::fake('public');

    $response = $this->post('/upload-files', [
        'fileId' => fakePngUpload('school-id.png'),
    ]);

    $response->assertRedirect('/login');
});

test('authenticated applicants can upload images and receive trusted metadata', function () {
    $this->withoutExceptionHandling();
    Storage::fake('public');

    $user = new IdpUser([
        'id' => '1234abcd',
        'role_id' => 1,
    ]);

    $response = $this->actingAs($user)->post('/upload-files', [
        'fileId' => fakePngUpload('school-id.png'),
    ]);

    $response
        ->assertOk()
        ->assertJsonPath('uploadedFiles.schoolId.originalName', 'school-id.png')
        ->assertJsonPath('uploadedFiles.schoolId.mimeType', 'image/webp')
        ->assertJsonPath('uploadedFiles.schoolId.isImage', true)
        ->assertJsonPath('uploadedFiles.schoolId.status', 'pending');

    $this->assertDatabaseHas('user_files', [
        'user_id' => $user->id,
        'type' => 'school_id',
        'original_name' => 'school-id.png',
        'status' => 'pending',
    ]);
});

test('preview uses trusted stored mime metadata and blocks non owners', function () {
    Storage::fake('public');

    $owner = new IdpUser(['id' => '1234', 'role_id' => 1]);
    $intruder = new IdpUser(['id' => '5678', 'role_id' => 1]);

    $storedFile = fakePngUpload('actual-image.png');
    $path = $storedFile->store('uploads/files', 'public');

    $userFile = UserFile::create([
        'user_id' => $owner->id,
        'type' => 'school_id',
        'file_path' => $path,
        'original_name' => 'spoofed.pdf',
        'status' => 'pending',
    ]);

    $signedUrl = URL::temporarySignedRoute(
        'files.preview',
        now()->addMinutes(5),
        ['file' => $userFile->id]
    );

    $this->actingAs($intruder)
        ->get($signedUrl)
        ->assertForbidden();

    $this->actingAs($owner)
        ->get($signedUrl)
        ->assertOk()
        ->assertHeader('Content-Type', 'image/png');
});
