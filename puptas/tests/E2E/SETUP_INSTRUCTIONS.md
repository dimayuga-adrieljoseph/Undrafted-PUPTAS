# E2E Test Setup Instructions

## Overview

E2E tests have been configured to use **real authentication** instead of mock sessions. This ensures tests run against actual application behavior.

## Prerequisites

Before running E2E tests, ensure:

1. **Database is running** (MySQL/MariaDB)
   - If using Docker: `docker-compose up -d mysql`
   - Or start your local MySQL/MariaDB service

2. **Laravel application is running**
   - The test suite will start `php artisan serve` automatically
   - But it requires database connectivity to boot

## Setup Steps

### 1. Start Database

```bash
# If using Docker Compose
docker-compose up -d mysql

# Wait for database to be ready (usually 10-30 seconds)
```

### 2. Create Test Users

Run the E2E test seeder to create test user accounts:

```bash
php artisan db:seed --class=E2ETestSeeder
```

This creates 4 test users:
- **Applicant**: `e2e.applicant@test.local` (password: `password`)
- **Admin**: `e2e.admin@test.local` (password: `password`)
- **Evaluator**: `e2e.evaluator@test.local` (password: `password`)
- **Interviewer**: `e2e.interviewer@test.local` (password: `password`)

### 3. Run E2E Tests

```bash
# Run all E2E tests
npm run test:e2e

# Run with UI (interactive)
npm run test:e2e:ui

# Run in headed mode (see browser)
npm run test:e2e:headed

# Debug mode
npm run test:e2e:debug
```

## How Authentication Works

### Test Authentication Controller

A special `TestAuthController` has been created **for testing purposes only**:

- **Location**: `app/Http/Controllers/TestAuthController.php`
- **Routes**: `/test/login` and `/test/logout`
- **Security**: Only enabled in `local` and `testing` environments
- **Purpose**: Allows E2E tests to authenticate without IDP

### Test Flow

Each E2E test file:

1. **Before Each Test**: Calls `/test/login` with test user email and role_id
2. **During Test**: Operates as an authenticated user with full session
3. **After Tests**: Can call `/test/logout` to clear session (optional)

Example from test file:

```javascript
test.beforeEach(async ({ page }) => {
  // Authenticate as test applicant
  await page.request.post('/test/login', {
    data: {
      email: 'e2e.applicant@test.local',
      role_id: 1, // Applicant
    },
  });

  await page.goto('/applicant-dashboard');
  await page.waitForLoadState('networkidle');
});
```

## Test Files Updated

All 6 E2E test files have been updated to use real authentication:

1. ✅ `01-public-status-checker.spec.js` - Public routes (no auth needed)
2. ✅ `02-authentication-flow.spec.js` - Auth flow tests
3. ✅ `03-applicant-dashboard-navigation.spec.js` - Applicant dashboard (7 tests)
4. ✅ `04-admin-dashboard-workflow.spec.js` - Admin dashboard (8 tests)
5. ✅ `05-evaluator-workflow.spec.js` - Evaluator dashboard (9 tests)
6. ✅ `06-interviewer-workflow.spec.js` - Interviewer dashboard (10 tests)

**Total**: 44 E2E tests

## Security Notes

⚠️ **IMPORTANT**: Test authentication routes are **disabled in production**

```php
// In routes/web.php
if (app()->environment(['local', 'testing'])) {
    Route::post('/test/login', [TestAuthController::class, 'testLogin']);
    Route::post('/test/logout', [TestAuthController::class, 'testLogout']);
}
```

These routes will return **404** in any other environment.

## Troubleshooting

### Tests Fail with "No connection could be made"

**Problem**: Database isn't running

**Solution**: 
```bash
docker-compose up -d mysql
# Wait 10-30 seconds, then retry
php artisan db:seed --class=E2ETestSeeder
```

### Tests Fail with "Timed out waiting from config.webServer"

**Problem**: Laravel server can't start (usually database connection issue)

**Solution**: 
1. Check `.env` file has correct database credentials
2. Ensure database is running
3. Test connection: `php artisan migrate:status`

### Tests Redirect to IDP Login

**Problem**: Test authentication isn't working

**Solution**: 
1. Verify test users exist in database
2. Check Laravel logs: `storage/logs/laravel.log`
3. Ensure you're running in `local` environment

### Some Tests Still Fail

**Expected**: Initial run may have some failures due to:
- Empty database (no applicant data)
- Missing programs/configuration
- Timing issues (increase timeouts if needed)

**Solution**: Tests are designed to be flexible and check for content existence rather than exact matches.

## Next Steps After Tests Pass

1. ✅ Review test results
2. ✅ Fix any remaining failures
3. ✅ Commit all E2E files to `feature/comprehensive-frontend-testing` branch
4. ✅ Update overall test README with final counts

## Files Created/Modified

### New Files:
- `app/Http/Controllers/TestAuthController.php` - Test authentication controller
- `database/seeders/E2ETestSeeder.php` - Test user seeder
- `tests/E2E/SETUP_INSTRUCTIONS.md` - This file

### Modified Files:
- `routes/web.php` - Added test auth routes
- `tests/E2E/03-applicant-dashboard-navigation.spec.js` - Updated to use real auth
- `tests/E2E/04-admin-dashboard-workflow.spec.js` - Updated to use real auth
- `tests/E2E/05-evaluator-workflow.spec.js` - Updated to use real auth
- `tests/E2E/06-interviewer-workflow.spec.js` - Updated to use real auth

## Commands Reference

```bash
# Database
docker-compose up -d mysql
docker-compose down

# Seeding
php artisan db:seed --class=E2ETestSeeder

# E2E Testing
npm run test:e2e              # Run all tests
npm run test:e2e:ui           # Interactive UI mode
npm run test:e2e:headed       # See browser
npm run test:e2e:debug        # Debug mode

# Laravel Server (manual if needed)
php artisan serve
```

---

**Status**: E2E tests are fully configured and ready to run once database is available.

**Pass Rate Goal**: 80-100% (44 tests)
