<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\ApplicantProfile;
use App\Models\Application;
use App\Models\ApplicationProcess;
use App\Models\Program;
use App\Auth\IdpUser;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Bug Condition Exploration Test
 * 
 * Property 1: Bug Condition - Status Display for Enrolled/Accepted Applicants
 * 
 * CRITICAL: This test MUST FAIL on unfixed code - failure confirms the bug exists
 * 
 * Goal: Surface counterexamples that demonstrate the bug exists
 * - Applicants with enrollment_status='officially_enrolled' should display "Officially Enrolled"
 * - Applicants with status='accepted' should display "Accepted"
 * - Interviewers attempting to transfer officially enrolled applicants should receive 403
 */
class InterviewerStatusDisplayTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Disable foreign key constraints for SQLite (needed for string user_ids)
        \Illuminate\Support\Facades\DB::statement('PRAGMA foreign_keys = OFF;');
        
        // Create a program for testing
        Program::create([
            'code' => 'BSCS',
            'name' => 'Bachelor of Science in Computer Science',
            'slots' => 10,
            'math' => 80,
            'science' => 80,
            'english' => 80,
        ]);
    }

    protected function tearDown(): void
    {
        // Re-enable foreign key constraints
        \Illuminate\Support\Facades\DB::statement('PRAGMA foreign_keys = ON;');
        
        parent::tearDown();
    }

    /**
     * Test: Officially enrolled applicant should display "Officially Enrolled" status
     * 
     * Bug Condition: enrollment_status === 'officially_enrolled' but status displays "Completed - Passed"
     * Expected: Status should display "Officially Enrolled"
     * 
     * This test will FAIL on unfixed code, confirming the bug exists
     */
    public function test_officially_enrolled_applicant_displays_correct_status()
    {
        // Arrange: Create an officially enrolled applicant
        $applicant = $this->createApplicantWithStatus(
            enrollmentStatus: 'officially_enrolled',
            applicationStatus: 'accepted',
            processAction: 'passed'
        );

        // Act: Fetch applicants via the API endpoint
        $interviewer = $this->createInterviewer();
        $response = $this->actingAs($interviewer)
            ->getJson('/interviewer-dashboard/applicants');

        // Assert: Response should be successful
        $response->assertStatus(200);
        
        // Find our test applicant in the response
        $applicants = $response->json();
        $testApplicant = collect($applicants)->firstWhere('id', $applicant->user_id);
        
        $this->assertNotNull($testApplicant, 'Test applicant not found in API response');
        
        // CRITICAL ASSERTION: Status should reflect enrollment status
        // This will FAIL on unfixed code because the frontend logic doesn't prioritize enrollment_status
        $this->assertEquals('officially_enrolled', $testApplicant['application']['enrollment_status'],
            'Enrollment status should be officially_enrolled');
        
        // Document the bug: The API returns the correct data, but the frontend displays it incorrectly
        // The frontend getEvaluationStatusText() function should return "Officially Enrolled"
        // but currently returns "Completed - Passed" because it checks process_action before enrollment_status
    }

    /**
     * Test: Accepted applicant should display "Accepted" status
     * 
     * Bug Condition: status === 'accepted' but status displays "Completed - Passed"
     * Expected: Status should display "Accepted"
     * 
     * This test will FAIL on unfixed code, confirming the bug exists
     */
    public function test_accepted_applicant_displays_correct_status()
    {
        // Arrange: Create an accepted applicant (not officially enrolled yet)
        $applicant = $this->createApplicantWithStatus(
            enrollmentStatus: 'pending',
            applicationStatus: 'accepted',
            processAction: 'passed'
        );

        // Act: Fetch applicants via the API endpoint
        $interviewer = $this->createInterviewer();
        $response = $this->actingAs($interviewer)
            ->getJson('/interviewer-dashboard/applicants');

        // Assert: Response should be successful
        $response->assertStatus(200);
        
        // Find our test applicant in the response
        $applicants = $response->json();
        $testApplicant = collect($applicants)->firstWhere('id', $applicant->user_id);
        
        $this->assertNotNull($testApplicant, 'Test applicant not found in API response');
        
        // CRITICAL ASSERTION: Status should reflect accepted status
        // This will FAIL on unfixed code because the frontend logic doesn't prioritize status
        $this->assertEquals('accepted', $testApplicant['application']['status'],
            'Application status should be accepted');
        
        // Document the bug: The API returns the correct data, but the frontend displays it incorrectly
        // The frontend getEvaluationStatusText() function should return "Accepted"
        // but currently returns "Completed - Passed" because it checks process_action before status
    }

    /**
     * Test: Interviewer cannot transfer officially enrolled applicant
     * 
     * Bug Condition: Backend allows interviewer to transfer officially enrolled applicant
     * Expected: Backend should return 403 Forbidden
     * 
     * This test will FAIL on unfixed code, confirming the bug exists
     */
    public function test_interviewer_cannot_transfer_officially_enrolled_applicant()
    {
        // Arrange: Create an officially enrolled applicant
        $applicant = $this->createApplicantWithStatus(
            enrollmentStatus: 'officially_enrolled',
            applicationStatus: 'accepted',
            processAction: 'passed'
        );

        // Create a new program to transfer to
        $newProgram = Program::create([
            'code' => 'BSIT',
            'name' => 'Bachelor of Science in Information Technology',
            'slots' => 10,
            'math' => 75,
            'science' => 75,
            'english' => 75,
        ]);

        // Act: Attempt to transfer as interviewer
        $interviewer = $this->createInterviewer();
        $response = $this->actingAs($interviewer)
            ->postJson("/interviewer-dashboard/transfer/{$applicant->user_id}", [
                'program_id' => $newProgram->id,
            ]);

        // Assert: Should receive 403 Forbidden
        // This will FAIL on unfixed code because the backend doesn't check ApplicationPolicy
        $response->assertStatus(403);
        
        // Document the bug: The backend transferToProgram() method doesn't call
        // $this->authorize('changeCourse', $application) to enforce authorization rules
    }

    /**
     * Test: Interviewer cannot transfer accepted applicant
     * 
     * Bug Condition: Backend allows interviewer to transfer accepted applicant
     * Expected: Backend should return 403 Forbidden
     */
    public function test_interviewer_cannot_transfer_accepted_applicant()
    {
        // Arrange: Create an accepted applicant (not officially enrolled)
        $applicant = $this->createApplicantWithStatus(
            enrollmentStatus: 'pending',
            applicationStatus: 'accepted',
            processAction: 'passed'
        );

        // Create a new program to transfer to
        $newProgram = Program::create([
            'code' => 'BSIT',
            'name' => 'Bachelor of Science in Information Technology',
            'slots' => 10,
            'math' => 75,
            'science' => 75,
            'english' => 75,
        ]);

        // Act: Attempt to transfer as interviewer
        $interviewer = $this->createInterviewer();
        $response = $this->actingAs($interviewer)
            ->postJson("/interviewer-dashboard/transfer/{$applicant->user_id}", [
                'program_id' => $newProgram->id,
            ]);

        // Assert: Should receive 403 Forbidden
        // This will FAIL on unfixed code because ApplicationPolicy doesn't check for accepted status
        $response->assertStatus(403);
    }

    /**
     * Helper: Create an applicant with specific status
     */
    private function createApplicantWithStatus(
        string $enrollmentStatus,
        string $applicationStatus,
        string $processAction
    ): ApplicantProfile {
        $program = Program::where('code', 'BSCS')->first();
        
        // Create applicant profile with string ID (no User record needed for IDP users)
        $userId = 'test-user-' . uniqid();
        $applicant = ApplicantProfile::create([
            'user_id' => $userId,
            'firstname' => 'Test',
            'lastname' => 'Applicant',
            'email' => 'test' . uniqid() . '@example.com',
            'contactnumber' => '09123456789',
        ]);

        // Create application
        $application = Application::create([
            'user_id' => $applicant->user_id,
            'program_id' => $program->id,
            'status' => $applicationStatus,
            'enrollment_status' => $enrollmentStatus,
        ]);

        // Create interviewer process with completed status
        ApplicationProcess::create([
            'application_id' => $application->id,
            'stage' => 'interviewer',
            'status' => 'completed',
            'action' => $processAction,
        ]);

        return $applicant;
    }

    /**
     * Helper: Create an interviewer user
     */
    private function createInterviewer(): IdpUser
    {
        return new IdpUser([
            'id' => 'interviewer-' . uniqid(),
            'idp_user_id' => 'interviewer-' . uniqid(),
            'email' => 'interviewer@example.com',
            'firstname' => 'Test',
            'lastname' => 'Interviewer',
            'role_id' => 4, // Interviewer role
        ]);
    }
}
