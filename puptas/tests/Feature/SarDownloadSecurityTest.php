<?php

use App\Models\User;
use App\Models\TestPasser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

/**
 * Test suite for SAR Download Security
 * 
 * Validates Requirements 3.1, 3.2, 3.3, 3.4, 3.5 from high-priority-security-fixes spec
 */

beforeEach(function () {
    // Set up fake storage for sar_tmp disk
    Storage::fake('sar_tmp');
});

test('unauthenticated user accessing SAR download redirects to login', function () {
    // Create a test passer with reference number
    $passer = TestPasser::create([
        'surname' => 'Doe',
        'first_name' => 'John',
        'middle_name' => 'M',
        'date_of_birth' => '2000-01-01',
        'address' => '123 Test St',
        'school_address' => '456 School Ave',
        'shs_school' => 'Test High School',
        'strand' => 'STEM',
        'year_graduated' => '2023',
        'email' => 'john.doe@example.com',
        'reference_number' => 'REF123456',
        'batch_number' => 'BATCH001',
        'school_year' => '2023-2024',
        'status' => 'pending',
    ]);

    $filename = 'SAR_REF123456_20240101.pdf';
    
    $response = $this->get(route('sar.passer-download', [
        'filename' => $filename,
        'reference' => 'REF123456',
    ]));
    
    $response->assertRedirect('/auth/idp/redirect');
});

test('authenticated user can download valid SAR PDF', function () {
    $user = User::factory()->create(['role_id' => 1]);
    
    // Create a test passer with reference number
    $passer = TestPasser::create([
        'surname' => 'Doe',
        'first_name' => 'John',
        'middle_name' => 'M',
        'date_of_birth' => '2000-01-01',
        'address' => '123 Test St',
        'school_address' => '456 School Ave',
        'shs_school' => 'Test High School',
        'strand' => 'STEM',
        'year_graduated' => '2023',
        'email' => 'john.doe@example.com',
        'reference_number' => 'REF123456',
        'batch_number' => 'BATCH001',
        'school_year' => '2023-2024',
        'status' => 'pending',
    ]);

    $filename = 'SAR_REF123456_20240101.pdf';
    
    // Create a fake PDF file
    Storage::disk('sar_tmp')->put($filename, 'fake pdf content');
    
    $response = $this->actingAs($user)->get(route('sar.passer-download', [
        'filename' => $filename,
        'reference' => 'REF123456',
    ]));
    
    $response->assertOk();
    $response->assertHeader('Content-Type', 'application/pdf');
});

test('path traversal with forward slash dot dot returns 400', function () {
    $user = User::factory()->create(['role_id' => 1]);
    
    // Create a test passer with reference number
    $passer = TestPasser::create([
        'surname' => 'Doe',
        'first_name' => 'John',
        'middle_name' => 'M',
        'date_of_birth' => '2000-01-01',
        'address' => '123 Test St',
        'school_address' => '456 School Ave',
        'shs_school' => 'Test High School',
        'strand' => 'STEM',
        'year_graduated' => '2023',
        'email' => 'john.doe2@example.com',
        'reference_number' => 'REF123456',
        'batch_number' => 'BATCH001',
        'school_year' => '2023-2024',
        'status' => 'pending',
    ]);

    $filename = '../../../etc/passwd';
    
    $response = $this->actingAs($user)->get(route('sar.passer-download', [
        'filename' => $filename,
        'reference' => 'REF123456',
    ]));
    
    $response->assertStatus(400);
});

test('path traversal with backslash dot dot returns 400', function () {
    $user = User::factory()->create(['role_id' => 1]);
    
    // Create a test passer with reference number
    $passer = TestPasser::create([
        'surname' => 'Doe',
        'first_name' => 'John',
        'middle_name' => 'M',
        'date_of_birth' => '2000-01-01',
        'address' => '123 Test St',
        'school_address' => '456 School Ave',
        'shs_school' => 'Test High School',
        'strand' => 'STEM',
        'year_graduated' => '2023',
        'email' => 'john.doe3@example.com',
        'reference_number' => 'REF123456',
        'batch_number' => 'BATCH001',
        'school_year' => '2023-2024',
        'status' => 'pending',
    ]);

    $filename = '..\\..\\windows\\system32\\config\\sam';
    
    $response = $this->actingAs($user)->get(route('sar.passer-download', [
        'filename' => $filename,
        'reference' => 'REF123456',
    ]));
    
    $response->assertStatus(400);
});

test('filename with special characters returns 400', function () {
    $user = User::factory()->create(['role_id' => 1]);
    
    // Create a test passer with reference number
    $passer = TestPasser::create([
        'surname' => 'Doe',
        'first_name' => 'John',
        'middle_name' => 'M',
        'date_of_birth' => '2000-01-01',
        'address' => '123 Test St',
        'school_address' => '456 School Ave',
        'shs_school' => 'Test High School',
        'strand' => 'STEM',
        'year_graduated' => '2023',
        'email' => 'john.doe4@example.com',
        'reference_number' => 'REF123456',
        'batch_number' => 'BATCH001',
        'school_year' => '2023-2024',
        'status' => 'pending',
    ]);

    $filename = 'SAR_REF123456_<script>alert(1)</script>.pdf';
    
    $response = $this->actingAs($user)->get(route('sar.passer-download', [
        'filename' => $filename,
        'reference' => 'REF123456',
    ]));
    
    $response->assertStatus(400);
});

test('filename with null bytes returns 400', function () {
    $user = User::factory()->create(['role_id' => 1]);
    
    // Create a test passer with reference number
    $passer = TestPasser::create([
        'surname' => 'Doe',
        'first_name' => 'John',
        'middle_name' => 'M',
        'date_of_birth' => '2000-01-01',
        'address' => '123 Test St',
        'school_address' => '456 School Ave',
        'shs_school' => 'Test High School',
        'strand' => 'STEM',
        'year_graduated' => '2023',
        'email' => 'john.doe5@example.com',
        'reference_number' => 'REF123456',
        'batch_number' => 'BATCH001',
        'school_year' => '2023-2024',
        'status' => 'pending',
    ]);

    $filename = "SAR_REF123456\x00.pdf";
    
    $response = $this->actingAs($user)->get(route('sar.passer-download', [
        'filename' => $filename,
        'reference' => 'REF123456',
    ]));
    
    $response->assertStatus(400);
});

test('filename with forward slash returns 400', function () {
    $user = User::factory()->create(['role_id' => 1]);
    
    // Create a test passer with reference number
    $passer = TestPasser::create([
        'surname' => 'Doe',
        'first_name' => 'John',
        'middle_name' => 'M',
        'date_of_birth' => '2000-01-01',
        'address' => '123 Test St',
        'school_address' => '456 School Ave',
        'shs_school' => 'Test High School',
        'strand' => 'STEM',
        'year_graduated' => '2023',
        'email' => 'john.doe6@example.com',
        'reference_number' => 'REF123456',
        'batch_number' => 'BATCH001',
        'school_year' => '2023-2024',
        'status' => 'pending',
    ]);

    $filename = 'path/to/SAR_REF123456.pdf';
    
    $response = $this->actingAs($user)->get(route('sar.passer-download', [
        'filename' => $filename,
        'reference' => 'REF123456',
    ]));
    
    $response->assertStatus(400);
});

test('filename with backslash returns 400', function () {
    $user = User::factory()->create(['role_id' => 1]);
    
    // Create a test passer with reference number
    $passer = TestPasser::create([
        'surname' => 'Doe',
        'first_name' => 'John',
        'middle_name' => 'M',
        'date_of_birth' => '2000-01-01',
        'address' => '123 Test St',
        'school_address' => '456 School Ave',
        'shs_school' => 'Test High School',
        'strand' => 'STEM',
        'year_graduated' => '2023',
        'email' => 'john.doe7@example.com',
        'reference_number' => 'REF123456',
        'batch_number' => 'BATCH001',
        'school_year' => '2023-2024',
        'status' => 'pending',
    ]);

    $filename = 'path\\to\\SAR_REF123456.pdf';
    
    $response = $this->actingAs($user)->get(route('sar.passer-download', [
        'filename' => $filename,
        'reference' => 'REF123456',
    ]));
    
    $response->assertStatus(400);
});

test('valid filename with alphanumeric dash underscore period is accepted', function () {
    $user = User::factory()->create(['role_id' => 1]);
    
    // Create a test passer with reference number
    $passer = TestPasser::create([
        'surname' => 'Doe',
        'first_name' => 'John',
        'middle_name' => 'M',
        'date_of_birth' => '2000-01-01',
        'address' => '123 Test St',
        'school_address' => '456 School Ave',
        'shs_school' => 'Test High School',
        'strand' => 'STEM',
        'year_graduated' => '2023',
        'email' => 'john.doe8@example.com',
        'reference_number' => 'REF123456',
        'batch_number' => 'BATCH001',
        'school_year' => '2023-2024',
        'status' => 'pending',
    ]);

    $filename = 'SAR_REF123456_2024-01-01_v1.0.pdf';
    
    // Create a fake PDF file
    Storage::disk('sar_tmp')->put($filename, 'fake pdf content');
    
    $response = $this->actingAs($user)->get(route('sar.passer-download', [
        'filename' => $filename,
        'reference' => 'REF123456',
    ]));
    
    $response->assertOk();
});

test('invalid reference number returns 404', function () {
    $user = User::factory()->create(['role_id' => 1]);
    
    $filename = 'SAR_INVALID_20240101.pdf';
    
    $response = $this->actingAs($user)->get(route('sar.passer-download', [
        'filename' => $filename,
        'reference' => 'INVALID',
    ]));
    
    $response->assertStatus(404);
});

test('mismatched filename and reference returns 403', function () {
    $user = User::factory()->create(['role_id' => 1]);
    
    // Create a test passer with reference number
    $passer = TestPasser::create([
        'surname' => 'Doe',
        'first_name' => 'John',
        'middle_name' => 'M',
        'date_of_birth' => '2000-01-01',
        'address' => '123 Test St',
        'school_address' => '456 School Ave',
        'shs_school' => 'Test High School',
        'strand' => 'STEM',
        'year_graduated' => '2023',
        'email' => 'john.doe10@example.com',
        'reference_number' => 'REF123456',
        'batch_number' => 'BATCH001',
        'school_year' => '2023-2024',
        'status' => 'pending',
    ]);

    // Filename doesn't match the reference number
    $filename = 'SAR_DIFFERENT_20240101.pdf';
    
    $response = $this->actingAs($user)->get(route('sar.passer-download', [
        'filename' => $filename,
        'reference' => 'REF123456',
    ]));
    
    $response->assertStatus(403);
});

test('non-existent file returns 404', function () {
    $user = User::factory()->create(['role_id' => 1]);
    
    // Create a test passer with reference number
    $passer = TestPasser::create([
        'surname' => 'Doe',
        'first_name' => 'John',
        'middle_name' => 'M',
        'date_of_birth' => '2000-01-01',
        'address' => '123 Test St',
        'school_address' => '456 School Ave',
        'shs_school' => 'Test High School',
        'strand' => 'STEM',
        'year_graduated' => '2023',
        'email' => 'john.doe11@example.com',
        'reference_number' => 'REF123456',
        'batch_number' => 'BATCH001',
        'school_year' => '2023-2024',
        'status' => 'pending',
    ]);

    $filename = 'SAR_REF123456_20240101.pdf';
    
    // Don't create the file - it should not exist
    
    $response = $this->actingAs($user)->get(route('sar.passer-download', [
        'filename' => $filename,
        'reference' => 'REF123456',
    ]));
    
    $response->assertStatus(404);
});
