<?php

use App\Models\User;
use App\Models\ApplicantProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Test suite for UserFileController::getUserApplication()
 * 
 * Validates Requirements 4.1, 4.2, 4.3, 4.4, 4.5, 4.6, 4.7 from high-priority-security-fixes spec
 */

test('getUserApplication retrieves school fields from ApplicantProfile', function () {
    // Create a user with an applicant profile
    $user = User::factory()->create([
        'role_id' => 1,
        'firstname' => 'John',
        'middlename' => 'M',
        'lastname' => 'Doe',
        'email' => 'john.doe@example.com',
        'birthday' => '2000-01-01',
        'sex' => 'Male',
        'contactnumber' => '09123456789',
        'street_address' => '123 Test St',
        'barangay' => 'Test Barangay',
        'city' => 'Test City',
        'province' => 'Test Province',
        'postal_code' => '1234',
    ]);

    // Create applicant profile with school information
    $profile = ApplicantProfile::create([
        'user_id' => $user->id,
        'email' => $user->email,
        'firstname' => $user->firstname,
        'lastname' => $user->lastname,
        'school' => 'Test High School',
        'school_address' => '456 School Ave',
        'date_graduated' => '2023-05-15',
        'strand' => 'STEM',
        'track' => 'Academic',
    ]);

    // Make authenticated request
    $response = $this->actingAs($user)->postJson('/get-files');

    // Assert response is successful
    $response->assertOk();

    // Assert school fields come from ApplicantProfile
    $response->assertJson([
        'school' => 'Test High School',
        'schoolAdd' => '456 School Ave',
        'dateGrad' => '2023-05-15',
        'strand' => 'STEM',
        'track' => 'Academic',
    ]);
});

test('getUserApplication returns non-null values when profile fields are populated', function () {
    // Create a user with an applicant profile
    $user = User::factory()->create([
        'role_id' => 1,
        'email' => 'jane.doe@example.com',
    ]);

    // Create applicant profile with all school fields populated
    $profile = ApplicantProfile::create([
        'user_id' => $user->id,
        'email' => $user->email,
        'firstname' => 'Jane',
        'lastname' => 'Doe',
        'school' => 'Another High School',
        'school_address' => '789 Education Blvd',
        'date_graduated' => '2022-03-20',
        'strand' => 'ABM',
        'track' => 'Technical-Vocational',
    ]);

    // Make authenticated request
    $response = $this->actingAs($user)->postJson('/get-files');

    // Assert response is successful
    $response->assertOk();

    // Assert all school fields are non-null
    $data = $response->json();
    expect($data['school'])->not->toBeNull();
    expect($data['schoolAdd'])->not->toBeNull();
    expect($data['dateGrad'])->not->toBeNull();
    expect($data['strand'])->not->toBeNull();
    expect($data['track'])->not->toBeNull();
});

test('getUserApplication correctly maps school_address to schoolAdd', function () {
    $user = User::factory()->create(['role_id' => 1]);

    $profile = ApplicantProfile::create([
        'user_id' => $user->id,
        'email' => $user->email,
        'firstname' => 'Test',
        'lastname' => 'User',
        'school_address' => 'Specific School Address 123',
    ]);

    $response = $this->actingAs($user)->postJson('/get-files');

    $response->assertOk();
    $response->assertJson([
        'schoolAdd' => 'Specific School Address 123',
    ]);
});

test('getUserApplication correctly maps date_graduated to dateGrad', function () {
    $user = User::factory()->create(['role_id' => 1]);

    $profile = ApplicantProfile::create([
        'user_id' => $user->id,
        'email' => $user->email,
        'firstname' => 'Test',
        'lastname' => 'User',
        'date_graduated' => '2021-06-30',
    ]);

    $response = $this->actingAs($user)->postJson('/get-files');

    $response->assertOk();
    $response->assertJson([
        'dateGrad' => '2021-06-30',
    ]);
});

test('getUserApplication handles missing ApplicantProfile gracefully', function () {
    // Create a user without an applicant profile
    $user = User::factory()->create([
        'role_id' => 1,
        'email' => 'no.profile@example.com',
    ]);

    // Make authenticated request
    $response = $this->actingAs($user)->postJson('/get-files');

    // Assert response is successful
    $response->assertOk();

    // Assert school fields are null when no profile exists
    $response->assertJson([
        'school' => null,
        'schoolAdd' => null,
        'dateGrad' => null,
        'strand' => null,
        'track' => null,
    ]);
});

test('getUserApplication returns user personal information correctly', function () {
    $user = User::factory()->create([
        'role_id' => 1,
        'firstname' => 'Alice',
        'middlename' => 'B',
        'lastname' => 'Smith',
        'email' => 'alice.smith@example.com',
        'birthday' => '1999-12-25',
        'sex' => 'Female',
        'contactnumber' => '09987654321',
        'street_address' => '789 Main St',
        'barangay' => 'Central',
        'city' => 'Metro City',
        'province' => 'Metro Province',
        'postal_code' => '5678',
    ]);

    $profile = ApplicantProfile::create([
        'user_id' => $user->id,
        'email' => $user->email,
        'firstname' => $user->firstname,
        'lastname' => $user->lastname,
    ]);

    $response = $this->actingAs($user)->postJson('/get-files');

    $response->assertOk();
    $response->assertJson([
        'firstname' => 'Alice',
        'middlename' => 'B',
        'lastname' => 'Smith',
        'email' => 'alice.smith@example.com',
        'birthday' => '1999-12-25',
        'sex' => 'Female',
        'contactnumber' => '09987654321',
        'street_address' => '789 Main St',
        'barangay' => 'Central',
        'city' => 'Metro City',
        'province' => 'Metro Province',
        'postal_code' => '5678',
    ]);
});
