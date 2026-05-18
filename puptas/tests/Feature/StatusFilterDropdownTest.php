<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\TestPasser;
use App\Models\PasserStatus;

class StatusFilterDropdownTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create passer statuses if they don't exist
        PasserStatus::firstOrCreate(['id' => 1], ['status' => 'qualified']);
        PasserStatus::firstOrCreate(['id' => 2], ['status' => 'waitlisted']);
        PasserStatus::firstOrCreate(['id' => 3], ['status' => 'unqualified']);
    }

    /** @test */
    public function test_test_passers_page_loads_successfully()
    {
        // Create a user with admin role (role_id = 2)
        $user = User::factory()->create([
            'role_id' => 2 // Admin role
        ]);

        // Create some test passers with different statuses
        TestPasser::factory()->create([
            'passer_status_id' => 1, // Qualified
            'surname' => 'Doe',
            'first_name' => 'John'
        ]);

        TestPasser::factory()->create([
            'passer_status_id' => 2, // Waitlisted
            'surname' => 'Smith',
            'first_name' => 'Jane'
        ]);

        TestPasser::factory()->create([
            'passer_status_id' => 3, // Unqualified
            'surname' => 'Johnson',
            'first_name' => 'Bob'
        ]);

        // Act as the user and visit the test passers page
        $response = $this->actingAs($user)
            ->get('/test-passers');

        // Assert that the page loads successfully
        $response->assertStatus(200);

        // Assert that the page contains Vue.js app structure
        $response->assertSee('id="app"', false);
    }

    /** @test */
    public function test_passer_statuses_exist_in_database()
    {
        // Verify that all required passer statuses exist
        $this->assertDatabaseHas('passer_statuses', ['id' => 1, 'status' => 'qualified']);
        $this->assertDatabaseHas('passer_statuses', ['id' => 2, 'status' => 'waitlisted']);
        $this->assertDatabaseHas('passer_statuses', ['id' => 3, 'status' => 'unqualified']);
    }

    /** @test */
    public function test_test_passer_can_have_different_statuses()
    {
        // Create test passers with different statuses
        $qualifiedPasser = TestPasser::factory()->create(['passer_status_id' => 1]);
        $waitlistedPasser = TestPasser::factory()->create(['passer_status_id' => 2]);
        $unqualifiedPasser = TestPasser::factory()->create(['passer_status_id' => 3]);

        // Verify the passers were created with correct statuses
        $this->assertEquals(1, $qualifiedPasser->passer_status_id);
        $this->assertEquals(2, $waitlistedPasser->passer_status_id);
        $this->assertEquals(3, $unqualifiedPasser->passer_status_id);

        // Verify the relationships work
        $this->assertEquals('qualified', $qualifiedPasser->passerStatus->status);
        $this->assertEquals('waitlisted', $waitlistedPasser->passerStatus->status);
        $this->assertEquals('unqualified', $unqualifiedPasser->passerStatus->status);
    }

    /** @test */
    public function test_vue_component_file_contains_status_filter()
    {
        // Read the Vue component file and verify it contains the status filter implementation
        $vueFilePath = resource_path('js/Pages/TestPassers/Email.vue');
        $this->assertFileExists($vueFilePath);
        
        $vueContent = file_get_contents($vueFilePath);
        
        // Check for the status filter reactive property
        $this->assertStringContainsString('filterPasserStatus', $vueContent);
        
        // Check for the status filter dropdown options
        $this->assertStringContainsString('All Statuses', $vueContent);
        $this->assertStringContainsString('Qualified', $vueContent);
        $this->assertStringContainsString('Waitlisted', $vueContent);
        $this->assertStringContainsString('Unqualified', $vueContent);
        
        // Check for the updated grid layout
        $this->assertStringContainsString('lg:grid-cols-5', $vueContent);
        
        // Check for the status filtering logic
        $this->assertStringContainsString('matchesStatus', $vueContent);
        
        // Check for the unqualified status display
        $this->assertStringContainsString('passer_status_id === 3', $vueContent);
        $this->assertStringContainsString('bg-red-100 text-red-800', $vueContent);
    }
}