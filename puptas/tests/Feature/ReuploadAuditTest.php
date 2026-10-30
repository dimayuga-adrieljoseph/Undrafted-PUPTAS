<?php

use App\Enums\RoleId;
use App\Models\Application;
use App\Models\AuditLog;
use App\Models\Program;
use App\Models\User;
use App\Models\UserFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

test('officially enrolled applicant calling POST /user/application/reupload gets 409 and existing file row and storage remain unchanged', function () {
    config(['filesystems.default' => 'public']);
    Storage::fake('public');

    $applicant = User::factory()->create([
        'firstname' => 'Juan',
        'lastname'  => 'Dela Cruz',
        'role_id'   => RoleId::Applicant->value,
    ]);

    $program = Program::create([
        'code'     => 'BSCS',
        'name'     => 'BS Computer Science',
        'capacity' => 50,
        'slots'    => 10,
    ]);

    Application::create([
        'user_id'           => $applicant->id,
        'program_id'        => $program->id,
        'status'            => 'accepted',
        'enrollment_status' => 'officially_enrolled',
    ]);

    UserFile::create([
        'user_id'       => $applicant->id,
        'type'          => 'file10_front',
        'file_path'     => 'uploads/files/initial-grade10.jpg',
        'original_name' => 'initial-grade10.jpg',
        'status'        => 'approved',
    ]);

    $response = $this->actingAs($applicant)->post('/user/application/reupload', [
        'field' => 'file10Front',
        'file'  => UploadedFile::fake()->image('replacement-grade10.jpg', 100, 100),
    ]);

    $response->assertStatus(409)
        ->assertJsonPath('message', 'Your enrollment is already finalized. Documents can no longer be changed.');

    $this->assertDatabaseHas('user_files', [
        'user_id'       => $applicant->id,
        'type'          => 'file10_front',
        'file_path'     => 'uploads/files/initial-grade10.jpg',
        'original_name' => 'initial-grade10.jpg',
        'status'        => 'approved',
    ]);

    expect(UserFile::where('user_id', $applicant->id)->count())->toBe(1);
    expect(Storage::disk('public')->allFiles('uploads/files'))->toBeEmpty();
});

test('temporary enrollment applicant can still upload fileCorFront via POST /user/application/reupload', function () {
    config(['filesystems.default' => 'public']);
    Storage::fake('public');

    $applicant = User::factory()->create([
        'firstname' => 'Ana',
        'lastname'  => 'Reyes',
        'role_id'   => RoleId::Applicant->value,
    ]);

    $program = Program::create([
        'code'     => 'BSIT',
        'name'     => 'BS Information Technology',
        'capacity' => 50,
        'slots'    => 10,
    ]);

    Application::create([
        'user_id'           => $applicant->id,
        'program_id'        => $program->id,
        'status'            => 'cleared_for_enrollment',
        'enrollment_status' => 'temporary',
    ]);

    $response = $this->actingAs($applicant)->post('/user/application/reupload', [
        'field' => 'fileCorFront',
        'file'  => UploadedFile::fake()->image('cor-front.jpg', 100, 100),
    ]);

    $response->assertStatus(200)
        ->assertJsonPath('message', 'File reuploaded successfully')
        ->assertJsonPath('file.originalName', 'cor-front.jpg')
        ->assertJsonPath('file.status', 'pending');

    $this->assertDatabaseHas('user_files', [
        'user_id'       => $applicant->id,
        'type'          => 'cor_front',
        'original_name' => 'cor-front.jpg',
        'status'        => 'pending',
    ]);
});

test('submitted applicant can still reupload via POST /user/application/reupload', function () {
    config(['filesystems.default' => 'public']);
    Storage::fake('public');

    $applicant = User::factory()->create([
        'firstname' => 'Pedro',
        'lastname'  => 'Santos',
        'role_id'   => RoleId::Applicant->value,
    ]);

    $program = Program::create([
        'code'     => 'BSIS',
        'name'     => 'BS Information Systems',
        'capacity' => 50,
        'slots'    => 10,
    ]);

    Application::create([
        'user_id'           => $applicant->id,
        'program_id'        => $program->id,
        'status'            => 'submitted',
        'enrollment_status' => 'pending',
    ]);

    UserFile::create([
        'user_id'       => $applicant->id,
        'type'          => 'file10_front',
        'file_path'     => 'uploads/files/submitted-grade10.jpg',
        'original_name' => 'submitted-grade10.jpg',
        'status'        => 'pending',
    ]);

    $response = $this->actingAs($applicant)->post('/user/application/reupload', [
        'field' => 'file10Front',
        'file'  => UploadedFile::fake()->image('updated-grade10.jpg', 100, 100),
    ]);

    $response->assertStatus(200)
        ->assertJsonPath('message', 'File reuploaded successfully')
        ->assertJsonPath('file.originalName', 'updated-grade10.jpg')
        ->assertJsonPath('file.status', 'pending');
});

test('officially enrolled applicant calling POST /upload-files gets 409 and existing file row and storage remain unchanged', function () {
    config(['filesystems.default' => 'public']);
    Storage::fake('public');

    $applicant = User::factory()->create([
        'firstname' => 'Carlo',
        'lastname'  => 'Mendoza',
        'role_id'   => RoleId::Applicant->value,
    ]);

    $program = Program::create([
        'code'     => 'BSCS2',
        'name'     => 'BS Computer Science 2',
        'capacity' => 50,
        'slots'    => 10,
    ]);

    Application::create([
        'user_id'           => $applicant->id,
        'program_id'        => $program->id,
        'status'            => 'accepted',
        'enrollment_status' => 'officially_enrolled',
    ]);

    UserFile::create([
        'user_id'       => $applicant->id,
        'type'          => 'file10_front',
        'file_path'     => 'uploads/files/initial-grade10.jpg',
        'original_name' => 'initial-grade10.jpg',
        'status'        => 'approved',
    ]);

    $response = $this->actingAs($applicant)->post('/upload-files', [
        'file10Front' => UploadedFile::fake()->image('replacement-grade10.jpg', 100, 100),
    ]);

    $response->assertStatus(409)
        ->assertJsonPath('message', 'Your enrollment is already finalized. Documents can no longer be changed.');

    $this->assertDatabaseHas('user_files', [
        'user_id'       => $applicant->id,
        'type'          => 'file10_front',
        'file_path'     => 'uploads/files/initial-grade10.jpg',
        'original_name' => 'initial-grade10.jpg',
        'status'        => 'approved',
    ]);

    expect(UserFile::where('user_id', $applicant->id)->count())->toBe(1);
    expect(Storage::disk('public')->allFiles('uploads/files'))->toBeEmpty();
});

test('reupload records previous and new original_name in audit log old_values and new_values', function () {
    config(['filesystems.default' => 'public']);
    Storage::fake('public');

    $applicant = User::factory()->create([
        'firstname' => 'Maria',
        'lastname'  => 'Clara',
        'role_id'   => RoleId::Applicant->value,
    ]);

    UserFile::create([
        'user_id'       => $applicant->id,
        'type'          => 'file10_front',
        'file_path'     => 'uploads/files/old-grade10.jpg',
        'original_name' => 'old-grade10.jpg',
        'status'        => 'pending',
    ]);

    $response = $this->actingAs($applicant)->post('/user/application/reupload', [
        'field' => 'file10Front',
        'file'  => UploadedFile::fake()->image('new-grade10.jpg', 100, 100),
    ]);

    $response->assertStatus(200)
        ->assertJsonPath('previous_original_name', 'old-grade10.jpg')
        ->assertJsonPath('new_original_name', 'new-grade10.jpg');

    $audit = AuditLog::latest('id')->first();
    expect($audit)->not->toBeNull();
    expect($audit->action_type)->toBe('UPDATE');
    expect($audit->module_name)->toBe('Applications');
    expect($audit->old_values)->toBe(['original_name' => 'old-grade10.jpg']);
    expect($audit->new_values)->toBe(['original_name' => 'new-grade10.jpg']);
    expect($audit->description)->toContain("original_name: 'old-grade10.jpg' -> 'new-grade10.jpg'");
});
