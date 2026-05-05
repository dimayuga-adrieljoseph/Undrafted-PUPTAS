<?php

namespace Tests\Unit;

use App\Http\Controllers\UserFileController;
use App\Models\User;
use App\Models\ApplicantProfile;
use App\Services\ImageCompressionService;
use App\Services\FileService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Unit tests for UserFileController::getUserApplication()
 * 
 * **Validates: Requirements 4.7**
 * 
 * These unit tests focus on the data retrieval logic of getUserApplication,
 * specifically testing that school fields are correctly retrieved from
 * ApplicantProfile and that non-null values are returned when profile
 * fields are populated.
 */
class UserFileControllerTest extends TestCase
{
    use RefreshDatabase;

    private UserFileController $controller;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create controller instance with mocked dependencies
        $compressionService = $this->createMock(ImageCompressionService::class);
        $fileService = $this->createMock(FileService::class);
        $this->controller = new UserFileController($compressionService, $fileService);
    }

    /** @test */
    public function it_retrieves_school_fields_from_applicant_profile(): void
    {
        // Create a user with an applicant profile
        $user = User::factory()->create([
            'role_id' => 1,
            'email' => 'test@example.com',
        ]);

        ApplicantProfile::create([
            'user_id' => $user->id,
            'email' => $user->email,
            'firstname' => 'Test',
            'lastname' => 'User',
            'school' => 'Test High School',
            'school_address' => '123 School Street',
            'date_graduated' => '2023-05-15',
            'strand' => 'STEM',
            'track' => 'Academic',
        ]);

        // Authenticate the user
        $this->actingAs($user);

        // Call the method
        $response = $this->controller->getUserApplication();
        $data = $response->getData(true);

        // Assert school fields are retrieved from ApplicantProfile
        $this->assertEquals('Test High School', $data['school']);
        $this->assertEquals('123 School Street', $data['schoolAdd']);
        $this->assertEquals('2023-05-15', $data['dateGrad']);
        $this->assertEquals('STEM', $data['strand']);
        $this->assertEquals('Academic', $data['track']);
    }

    /** @test */
    public function it_returns_non_null_values_when_profile_fields_are_populated(): void
    {
        // Create a user with fully populated applicant profile
        $user = User::factory()->create([
            'role_id' => 1,
            'email' => 'populated@example.com',
        ]);

        ApplicantProfile::create([
            'user_id' => $user->id,
            'email' => $user->email,
            'firstname' => 'Jane',
            'lastname' => 'Doe',
            'school' => 'Complete High School',
            'school_address' => '456 Education Ave',
            'date_graduated' => '2022-03-20',
            'strand' => 'ABM',
            'track' => 'Technical-Vocational',
        ]);

        // Authenticate the user
        $this->actingAs($user);

        // Call the method
        $response = $this->controller->getUserApplication();
        $data = $response->getData(true);

        // Assert all school fields are non-null (Requirement 4.7)
        $this->assertNotNull($data['school'], 'school field should not be null when populated');
        $this->assertNotNull($data['schoolAdd'], 'schoolAdd field should not be null when populated');
        $this->assertNotNull($data['dateGrad'], 'dateGrad field should not be null when populated');
        $this->assertNotNull($data['strand'], 'strand field should not be null when populated');
        $this->assertNotNull($data['track'], 'track field should not be null when populated');
    }

    /** @test */
    public function it_correctly_maps_school_address_to_school_add(): void
    {
        $user = User::factory()->create(['role_id' => 1]);

        ApplicantProfile::create([
            'user_id' => $user->id,
            'email' => $user->email,
            'firstname' => 'Test',
            'lastname' => 'User',
            'school_address' => 'Specific Address 789',
        ]);

        $this->actingAs($user);

        $response = $this->controller->getUserApplication();
        $data = $response->getData(true);

        // Assert the field mapping is correct
        $this->assertEquals('Specific Address 789', $data['schoolAdd']);
    }

    /** @test */
    public function it_correctly_maps_date_graduated_to_date_grad(): void
    {
        $user = User::factory()->create(['role_id' => 1]);

        ApplicantProfile::create([
            'user_id' => $user->id,
            'email' => $user->email,
            'firstname' => 'Test',
            'lastname' => 'User',
            'date_graduated' => '2021-06-30',
        ]);

        $this->actingAs($user);

        $response = $this->controller->getUserApplication();
        $data = $response->getData(true);

        // Assert the field mapping is correct
        $this->assertEquals('2021-06-30', $data['dateGrad']);
    }

    /** @test */
    public function it_handles_missing_applicant_profile_gracefully(): void
    {
        // Create a user without an applicant profile
        $user = User::factory()->create([
            'role_id' => 1,
            'email' => 'no.profile@example.com',
        ]);

        $this->actingAs($user);

        $response = $this->controller->getUserApplication();
        $data = $response->getData(true);

        // Assert school fields are null when no profile exists
        $this->assertNull($data['school']);
        $this->assertNull($data['schoolAdd']);
        $this->assertNull($data['dateGrad']);
        $this->assertNull($data['strand']);
        $this->assertNull($data['track']);
    }

    /** @test */
    public function it_handles_partially_populated_profile(): void
    {
        $user = User::factory()->create(['role_id' => 1]);

        // Create profile with only some fields populated
        ApplicantProfile::create([
            'user_id' => $user->id,
            'email' => $user->email,
            'firstname' => 'Test',
            'lastname' => 'User',
            'school' => 'Partial School',
            // school_address, date_graduated, strand, track are null
        ]);

        $this->actingAs($user);

        $response = $this->controller->getUserApplication();
        $data = $response->getData(true);

        // Assert populated field is returned
        $this->assertEquals('Partial School', $data['school']);
        
        // Assert unpopulated fields are null
        $this->assertNull($data['schoolAdd']);
        $this->assertNull($data['dateGrad']);
        $this->assertNull($data['strand']);
        $this->assertNull($data['track']);
    }

    /** @test */
    public function it_returns_user_personal_information(): void
    {
        $user = User::factory()->create([
            'role_id' => 1,
            'firstname' => 'John',
            'middlename' => 'M',
            'lastname' => 'Smith',
            'email' => 'john.smith@example.com',
            'birthday' => '1999-12-25',
            'sex' => 'Male',
            'contactnumber' => '09123456789',
            'street_address' => '789 Main St',
            'barangay' => 'Central',
            'city' => 'Metro City',
            'province' => 'Metro Province',
            'postal_code' => '5678',
        ]);

        ApplicantProfile::create([
            'user_id' => $user->id,
            'email' => $user->email,
            'firstname' => $user->firstname,
            'lastname' => $user->lastname,
        ]);

        $this->actingAs($user);

        $response = $this->controller->getUserApplication();
        $data = $response->getData(true);

        // Assert user personal information is correctly returned
        $this->assertEquals('John', $data['firstname']);
        $this->assertEquals('M', $data['middlename']);
        $this->assertEquals('Smith', $data['lastname']);
        $this->assertEquals('john.smith@example.com', $data['email']);
        $this->assertEquals('1999-12-25', $data['birthday']);
        $this->assertEquals('Male', $data['sex']);
        $this->assertEquals('09123456789', $data['contactnumber']);
        $this->assertEquals('789 Main St', $data['street_address']);
        $this->assertEquals('Central', $data['barangay']);
        $this->assertEquals('Metro City', $data['city']);
        $this->assertEquals('Metro Province', $data['province']);
        $this->assertEquals('5678', $data['postal_code']);
    }
}
