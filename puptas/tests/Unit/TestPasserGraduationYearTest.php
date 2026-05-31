<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\TestPasser;
use App\Models\ApplicantProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

class TestPasserGraduationYearTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that graduation_year uses year_graduated when set
     */
    public function test_graduation_year_uses_year_graduated_when_set()
    {
        $passer = TestPasser::factory()->create([
            'year_graduated' => '2023',
            'user_id' => null,
        ]);

        $this->assertEquals('2023', $passer->graduation_year);
    }

    /**
     * Test that graduation_year extracts year from date_graduated in profile
     */
    public function test_graduation_year_uses_date_graduated_from_profile()
    {
        // Create user with applicant profile
        $user = User::factory()->create(['role_id' => 1]);
        
        $profile = ApplicantProfile::create([
            'user_id' => $user->id,
            'email' => $user->email,
            'firstname' => 'Test',
            'lastname' => 'User',
            'date_graduated' => '2024-05-15',
        ]);

        // Create test passer linked to this user (no year_graduated set)
        $passer = TestPasser::factory()->create([
            'user_id' => $user->id,
            'year_graduated' => null,
        ]);

        // Should extract year from profile's date_graduated
        $this->assertEquals('2024', $passer->graduation_year);
    }

    /**
     * Test that year_graduated takes priority over date_graduated
     */
    public function test_year_graduated_takes_priority_over_date_graduated()
    {
        // Create user with applicant profile
        $user = User::factory()->create(['role_id' => 1]);
        
        $profile = ApplicantProfile::create([
            'user_id' => $user->id,
            'email' => $user->email,
            'firstname' => 'Test',
            'lastname' => 'User',
            'date_graduated' => '2024-05-15', // Profile says 2024
        ]);

        // Create test passer with explicit year_graduated
        $passer = TestPasser::factory()->create([
            'user_id' => $user->id,
            'year_graduated' => '2023', // But year_graduated says 2023
        ]);

        // Should use year_graduated (priority)
        $this->assertEquals('2023', $passer->graduation_year);
    }

    /**
     * Test that graduation_year falls back to current year when no data available
     */
    public function test_graduation_year_falls_back_to_current_year()
    {
        $passer = TestPasser::factory()->create([
            'year_graduated' => null,
            'user_id' => null,
        ]);

        $currentYear = date('Y');
        $this->assertEquals($currentYear, $passer->graduation_year);
    }

    /**
     * Test that graduation_year handles profile without date_graduated
     */
    public function test_graduation_year_handles_profile_without_date_graduated()
    {
        // Create user with applicant profile (no date_graduated)
        $user = User::factory()->create(['role_id' => 1]);
        
        $profile = ApplicantProfile::create([
            'user_id' => $user->id,
            'email' => $user->email,
            'firstname' => 'Test',
            'lastname' => 'User',
            'date_graduated' => null,
        ]);

        // Create test passer linked to this user
        $passer = TestPasser::factory()->create([
            'user_id' => $user->id,
            'year_graduated' => null,
        ]);

        // Should fall back to current year
        $currentYear = date('Y');
        $this->assertEquals($currentYear, $passer->graduation_year);
    }

    /**
     * Test that graduation_year returns string format
     */
    public function test_graduation_year_returns_string()
    {
        $passer = TestPasser::factory()->create([
            'year_graduated' => 2023, // Integer
        ]);

        $result = $passer->graduation_year;
        $this->assertIsString($result);
        $this->assertEquals('2023', $result);
    }
}
