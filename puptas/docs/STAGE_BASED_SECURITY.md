# Stage-Based Application Visibility Security

## Overview
This document describes the implementation of stage-based visibility for applications in the PUPTAS system. Applications are now only visible to staff members based on the current stage of the application process.

## Security Implementation

### Core Principle
**Applications are only visible to staff members when the application is at their respective stage.** This ensures that:
- Evaluators only see applications pending evaluation
- Interviewers only see applications pending interviews
- Medical staff only see applications pending medical clearance
- Record staff only see applications pending records processing
- **Admins can see all applications at all times**

### Technical Implementation

#### 1. UserService - Stage-Based Filtering
**File:** `app/Services/UserService.php`

Added method: `getApplicantsByStage(string $stage): Collection`

This method filters applicants based on the `ApplicationProcess` table, only returning users whose applications have an active process (status: `in_progress` or `returned`) at the specified stage.

```php
public function getApplicantsByStage(string $stage): Collection
{
    return User::with('currentApplication.program')
        ->where('role_id', 1)
        ->whereHas('currentApplication', function ($query) use ($stage) {
            $query->whereHas('processes', function ($q) use ($stage) {
                $q->where('stage', $stage)
                  ->whereIn('status', ['in_progress', 'returned']);
            });
        })
        ->get()
        // ... mapping logic
}
```

#### 2. Controller Updates
**Updated Files:**
- `app/Http/Controllers/EvaluatorDashboardController.php`
- `app/Http/Controllers/InterviewerDashboardController.php`
- `app/Http/Controllers/MedicalDashboardController.php`
- `app/Http/Controllers/RecordStaffDashboardController.php`

Each controller's `getUsers()` method now uses stage-based filtering:

```php
public function getUsers()
{
    // Only return applicants currently at [stage] stage
    return response()->json($this->userService->getApplicantsByStage('[stage]'));
}
```

**Admin Override:** The admin's `DashboardController` continues to use `getApplicantsWithApplications()` to see all applications regardless of stage.

#### 3. Individual Application Access Protection
**File:** `app/Http/Traits/ManagesApplicationFiles.php`

Updated `getUserFiles()` method to verify stage-based access:

```php
public function getUserFiles($id)
{
    // ... user loading logic
    
    // Security check: Verify the user's application is at the appropriate stage
    // Admin (role_id 2) can bypass this check
    if (auth()->user()->role_id !== 2) {
        $currentStage = $this->getCurrentStage();
        $hasAccess = $application->processes()
            ->where('stage', $currentStage)
            ->whereIn('status', ['in_progress', 'returned'])
            ->exists();

        if (!$hasAccess) {
            return response()->json([
                'message' => 'Unauthorized access. Application is not at the ' . $currentStage . ' stage.'
            ], 403);
        }
    }
    // ... return files
}
```

#### 4. Route Protection
**File:** `routes/web.php`

All role-specific routes are now wrapped in authenticated middleware groups:

```php
Route::middleware(['auth'])->group(function () {
    // Stage-specific dashboard and API endpoints
    Route::get('/[role]-dashboard', [...]);
    Route::get('/[role]-dashboard/applicants', [...]);
    Route::get('/[role]-dashboard/application/{id}', [...]);
    // ... other endpoints
});
```

### Application Stages

The system recognizes the following stages:

1. **evaluator** - Initial document evaluation
2. **interviewer** - Interview process
3. **medical** - Medical clearance
4. **records** - Final records processing

### Process Status Values

Applications can have the following process statuses:
- **in_progress** - Currently being processed at this stage
- **returned** - Returned to applicant for corrections, still at this stage
- **completed** - Stage is complete, application has moved forward

### Security Layers

The implementation provides multiple layers of security:

1. **Authentication Middleware** - Ensures user is logged in
2. **Role Verification** - Each controller verifies the user has the correct role via `ensureRole()`
3. **Stage-Based Filtering** - Users only see applications at their stage
4. **Individual Access Control** - When viewing a specific application, verify it's at the correct stage
5. **Action Authorization** - Each action (accept, return, transfer) verifies the application is in the correct state

### Admin Privileges

Users with `role_id = 2` (Admin) have unrestricted access:
- Can view all applications at all stages
- Stage filtering is bypassed for admins
- API endpoints return all applicants for admins

### Testing the Implementation

To verify the security implementation:

1. **Test Stage-Based List Filtering:**
   - Login as Evaluator → Should only see applications with evaluator stage in_progress/returned
   - Login as Interviewer → Should only see applications with interviewer stage in_progress/returned
   - Login as Medical → Should only see applications with medical stage in_progress/returned
   - Login as Record Staff → Should only see applications with records stage in_progress/returned
   - Login as Admin → Should see ALL applications

2. **Test Individual Application Access:**
   - Attempt to access an application detail page for an application not at your stage
   - Expected: 403 Forbidden error
   - Admin should be able to access any application

3. **Test Action Authorization:**
   - Attempt to accept/return an application not at your stage
   - Expected: Error message indicating action is not available

### Role ID Reference

- `1` - Applicant
- `2` - Admin
- `3` - Evaluator
- `4` - Interviewer
- `5` - Medical (Nurse)
- `6` - Record Staff

### Security Best Practices Followed

✅ **Defense in Depth** - Multiple layers of security checks
✅ **Server-Side Validation** - All filtering happens on the backend
✅ **Principle of Least Privilege** - Users only see what they need
✅ **Fail-Safe Defaults** - Access denied unless explicitly granted
✅ **Admin Override** - Privileged users maintain full access
✅ **Audit Trail Ready** - Existing audit logging captures access patterns

### Future Enhancements

Potential improvements for consideration:
- Add logging for unauthorized access attempts
- Implement rate limiting on API endpoints
- Add more granular permissions (view vs. modify)
- Consider implementing Laravel Policies for cleaner authorization logic
- Add unit tests for authorization checks

## Conclusion

The stage-based security implementation ensures that application data is only accessible to the appropriate staff members at the appropriate time, while maintaining admin oversight. This follows security best practices and protects sensitive applicant information.
