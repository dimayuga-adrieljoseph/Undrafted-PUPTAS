<?php

use App\Models\User;
use App\Models\UserFile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

function makeTestUser(array $overrides = []): User
{
    static $sequence = 1;

    $user = User::create(array_merge([
        'firstname' => 'Test',
        'lastname' => 'User' . $sequence,
        'contactnumber' => '0917000000' . $sequence,
        'email' => 'user' . $sequence . '@example.com',
        'password' => Hash::make('password'),
        'role_id' => 1,
        'privacy_consent' => true,
        'privacy_consent_at' => now(),
        'email_verified_at' => now(),
    ], $overrides));

    $sequence++;

    return $user;
}

function fakePngUpload(string $filename = 'image.png'): UploadedFile
{
    return UploadedFile::fake()->createWithContent(
        $filename,
        base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9sot5AAAAABJRU5ErkJggg==')
    );
}

test('guests cannot upload applicant files', function () {
    Storage::fake('public');

    $response = $this->post('/upload-files', [
        'fileId' => fakePngUpload('school-id.png'),
    ]);

    $response->assertRedirect('/login');
});

test('authenticated applicants can upload images and receive trusted metadata', function () {
    Storage::fake('public');

    $user = makeTestUser();

    $response = $this->actingAs($user)->post('/upload-files', [
        'fileId' => fakePngUpload('school-id.png'),
    ]);

    $response
        ->assertOk()
        ->assertJsonPath('uploadedFiles.schoolId.originalName', 'school-id.png')
        ->assertJsonPath('uploadedFiles.schoolId.mimeType', 'image/png')
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

    $owner = makeTestUser();

    $intruder = makeTestUser();

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
        ->assertHeader('Content-Type', 'image/png')
        ->assertHeader('X-Content-Type-Options', 'nosniff');
});