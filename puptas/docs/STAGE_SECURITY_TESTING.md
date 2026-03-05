# Stage-Based Security Testing Plan

## Test Environment Setup

### Prerequisites
- PHP and Laravel environment running
- Database seeded with test users for each role
- At least 4 test applications at different stages

### Test Data Required

#### Users
- Admin (role_id: 2)
- Evaluator (role_id: 3)  
- Interviewer (role_id: 4)
- Medical Staff (role_id: 5)
- Record Staff (role_id: 6)

#### Applications
- Application A: At evaluator stage (status: in_progress)
- Application B: At interviewer stage (status: in_progress)
- Application C: At medical stage (status: in_progress)
- Application D: At records stage (status: in_progress)

## Test Cases

### 1. List View - Stage-Based Filtering

#### Test 1.1: Evaluator Can Only See Evaluator Stage Applications
**Steps:**
1. Login as Evaluator
2. Navigate to `/evaluator-dashboard`
3. Verify application list

**Expected Result:**
- Only Application A should be visible
- Applications B, C, D should NOT appear

**API Test:**
```bash
curl -X GET http://localhost/evaluator-dashboard/applicants \
  -H "Cookie: [session_cookie]" \
  -H "Accept: application/json"
```

#### Test 1.2: Interviewer Can Only See Interviewer Stage Applications
**Steps:**
1. Login as Interviewer
2. Navigate to `/interviewer-dashboard`
3. Verify application list

**Expected Result:**
- Only Application B should be visible
- Applications A, C, D should NOT appear

**API Test:**
```bash
curl -X GET http://localhost/interviewer-dashboard/applicants \
  -H "Cookie: [session_cookie]" \
  -H "Accept: application/json"
```

#### Test 1.3: Medical Staff Can Only See Medical Stage Applications
**Steps:**
1. Login as Medical Staff
2. Navigate to `/medical-dashboard`
3. Verify application list

**Expected Result:**
- Only Application C should be visible
- Applications A, B, D should NOT appear

**API Test:**
```bash
curl -X GET http://localhost/medical-dashboard/applicants \
  -H "Cookie: [session_cookie]" \
  -H "Accept: application/json"
```

#### Test 1.4: Record Staff Can Only See Records Stage Applications
**Steps:**
1. Login as Record Staff
2. Navigate to `/record-dashboard`
3. Verify application list

**Expected Result:**
- Only Application D should be visible
- Applications A, B, C should NOT appear

**API Test:**
```bash
curl -X GET http://localhost/record-dashboard/applicants \
  -H "Cookie: [session_cookie]" \
  -H "Accept: application/json"
```

#### Test 1.5: Admin Can See All Applications
**Steps:**
1. Login as Admin
2. Navigate to `/dashboard`
3. Verify application list

**Expected Result:**
- All applications (A, B, C, D) should be visible

**API Test:**
```bash
curl -X GET http://localhost/dashboard/users \
  -H "Cookie: [session_cookie]" \
  -H "Accept: application/json"
```

### 2. Individual Application Access

#### Test 2.1: Unauthorized Stage Access - Evaluator Accessing Interview Stage
**Steps:**
1. Login as Evaluator
2. Attempt to access Application B details (interviewer stage)

**API Test:**
```bash
curl -X GET http://localhost/dashboard/user-files/{application_b_user_id} \
  -H "Cookie: [evaluator_session]" \
  -H "Accept: application/json"
```

**Expected Result:**
- HTTP 403 Forbidden
- Error message: "Unauthorized access. Application is not at the evaluator stage."

#### Test 2.2: Authorized Stage Access - Evaluator Accessing Evaluator Stage
**Steps:**
1. Login as Evaluator
2. Attempt to access Application A details (evaluator stage)

**API Test:**
```bash
curl -X GET http://localhost/dashboard/user-files/{application_a_user_id} \
  -H "Cookie: [evaluator_session]" \
  -H "Accept: application/json"
```

**Expected Result:**
- HTTP 200 OK
- Application details and files returned

#### Test 2.3: Admin Access Override
**Steps:**
1. Login as Admin
2. Attempt to access any application details (any stage)

**API Test:**
```bash
curl -X GET http://localhost/dashboard/user-files/{any_application_id} \
  -H "Cookie: [admin_session]" \
  -H "Accept: application/json"
```

**Expected Result:**
- HTTP 200 OK
- Application details returned regardless of stage

### 3. Action Authorization

#### Test 3.1: Unauthorized Action - Interviewer Accepting Evaluator Stage App
**Steps:**
1. Login as Interviewer
2. Attempt to accept Application A (evaluator stage)

**API Test:**
```bash
curl -X POST http://localhost/interviewer-dashboard/accept/{application_a_user_id} \
  -H "Cookie: [interviewer_session]" \
  -H "Accept: application/json"
```

**Expected Result:**
- HTTP 409 Conflict or 403 Forbidden
- Error message: "This action has already been completed or is not available."

#### Test 3.2: Authorized Action - Interviewer Accepting Interview Stage App
**Steps:**
1. Login as Interviewer
2. Accept Application B (interviewer stage)

**API Test:**
```bash
curl -X POST http://localhost/interviewer-dashboard/accept/{application_b_user_id} \
  -H "Cookie: [interviewer_session]" \
  -H "Accept: application/json"
```

**Expected Result:**
- HTTP 200 OK
- Application moves to medical stage
- Success message returned

### 4. Cross-Stage Visibility

#### Test 4.1: Application Moved Between Stages
**Steps:**
1. Login as Evaluator
2. Verify Application A is visible
3. Pass Application A to interviewer stage
4. Verify Application A is no longer visible in evaluator list
5. Login as Interviewer
6. Verify Application A now appears in interviewer list

**Expected Result:**
- Application disappears from evaluator list once passed
- Application appears in interviewer list immediately

### 5. Security - Unauthenticated Access

#### Test 5.1: Accessing API Endpoints Without Authentication
**API Test:**
```bash
curl -X GET http://localhost/evaluator-dashboard/applicants \
  -H "Accept: application/json"
```

**Expected Result:**
- HTTP 302 Redirect to login page OR
- HTTP 401 Unauthorized

### 6. Security - Role Bypass Attempt

#### Test 6.1: Wrong Role Accessing Endpoint
**Steps:**
1. Login as Interviewer
2. Attempt to access evaluator-specific endpoint

**API Test:**
```bash
curl -X GET http://localhost/evaluator-dashboard/applicants \
  -H "Cookie: [interviewer_session]" \
  -H "Accept: application/json"
```

**Expected Result:**
- HTTP 403 Forbidden
- Rejected by `ensureRole()` check

### 7. Returned Applications

#### Test 7.1: Returned Applications Still Visible at Current Stage
**Steps:**
1. Login as Interviewer
2. Return Application B to applicant for corrections
3. Verify Application B is still visible in interviewer list
4. Login as Evaluator
5. Verify Application B is NOT visible in evaluator list

**Expected Result:**
- Returned applications remain visible at the stage that returned them
- They do NOT appear at previous stages

## Test Execution Checklist

- [ ] Test 1.1: Evaluator list filtering
- [ ] Test 1.2: Interviewer list filtering
- [ ] Test 1.3: Medical list filtering
- [ ] Test 1.4: Record staff list filtering
- [ ] Test 1.5: Admin sees all
- [ ] Test 2.1: Unauthorized access blocked
- [ ] Test 2.2: Authorized access allowed
- [ ] Test 2.3: Admin override works
- [ ] Test 3.1: Unauthorized action blocked
- [ ] Test 3.2: Authorized action succeeds
- [ ] Test 4.1: Visibility updates when stage changes
- [ ] Test 5.1: Unauthenticated access blocked
- [ ] Test 6.1: Wrong role access blocked
- [ ] Test 7.1: Returned apps remain at current stage

## Automated Test Example (PHPUnit)

```php
<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Application;
use App\Models\ApplicationProcess;
use Illuminate\Foundation\Testing\RefreshDatabase;

class StageBasedSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_evaluator_only_sees_evaluator_stage_applications()
    {
        // Arrange
        $evaluator = User::factory()->create(['role_id' => 3]);
        
        $evaluatorApp = Application::factory()->create();
        ApplicationProcess::create([
            'application_id' => $evaluatorApp->id,
            'stage' => 'evaluator',
            'status' => 'in_progress',
        ]);
        
        $interviewerApp = Application::factory()->create();
        ApplicationProcess::create([
            'application_id' => $interviewerApp->id,
            'stage' => 'interviewer',
            'status' => 'in_progress',
        ]);

        // Act
        $response = $this->actingAs($evaluator)
            ->getJson('/evaluator-dashboard/applicants');

        // Assert
        $response->assertStatus(200);
        $data = $response->json();
        $this->assertCount(1, $data);
        $this->assertEquals($evaluatorApp->user_id, $data[0]['id']);
    }

    public function test_interviewer_cannot_access_evaluator_stage_application()
    {
        // Arrange
        $interviewer = User::factory()->create(['role_id' => 4]);
        
        $evaluatorApp = Application::factory()->create();
        ApplicationProcess::create([
            'application_id' => $evaluatorApp->id,
            'stage' => 'evaluator',
            'status' => 'in_progress',
        ]);

        // Act
        $response = $this->actingAs($interviewer)
            ->getJson('/interviewer-dashboard/application/' . $evaluatorApp->user_id);

        // Assert
        $response->assertStatus(403);
        $response->assertJson([
            'message' => 'Unauthorized access. Application is not at the interviewer stage.'
        ]);
    }

    public function test_admin_can_access_all_applications()
    {
        // Arrange
        $admin = User::factory()->create(['role_id' => 2]);
        
        $evaluatorApp = Application::factory()->create();
        ApplicationProcess::create([
            'application_id' => $evaluatorApp->id,
            'stage' => 'evaluator',
            'status' => 'in_progress',
        ]);

        // Act
        $response = $this->actingAs($admin)
            ->getJson('/dashboard/user-files/' . $evaluatorApp->user_id);

        // Assert
        $response->assertStatus(200);
    }
}
```

## Success Criteria

All tests must pass with:
✅ Correct data filtering at each stage
✅ Proper access control for individual applications
✅ Admin override functioning correctly
✅ Authentication checks working
✅ Role verification preventing cross-role access
✅ Applications moving between stages correctly

## Regression Testing

After implementation, verify:
- [ ] Existing functionality still works
- [ ] Dashboard displays load correctly
- [ ] Actions (accept, return, transfer, tag) still function
- [ ] No performance degradation
- [ ] Audit logs still capture actions
