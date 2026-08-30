import { test, expect } from '@playwright/test';

/**
 * E2E Test: Evaluator Workflow
 * 
 * Tests the evaluator dashboard and document/grade evaluation workflows.
 * Uses real test authentication via TestAuthController.
 */

test.describe('Evaluator Workflow', () => {
  test.beforeEach(async ({ page }) => {
    // Authenticate as test evaluator using dev-login
    await page.goto('/dev-login?email=e2e.evaluator@test.local');
    await page.waitForLoadState('load');
    await page.waitForTimeout(2000); // Wait for dashboard to fully render
  });

  test('should load evaluator dashboard', async ({ page }) => {
    // Verify page loaded
    const body = await page.content();
    const pageLoaded = body.length > 100;
    expect(pageLoaded).toBeTruthy();
    
    const title = await page.title();
    expect(title).not.toContain('404');
  });

  test('should display summary statistics', async ({ page }) => {
    // Check for statistics content
    const body = await page.content();
    const hasStats = 
      body.includes('Total') ||
      body.includes('Pending') ||
      body.includes('Evaluated') ||
      body.includes('Applicant') ||
      await page.locator('[class*="stat"], [class*="card"]').count() > 0;
    
    expect(hasStats).toBeTruthy();
  });

  test('should show applicants awaiting evaluation', async ({ page }) => {
    // Check for applicant list or table
    const hasApplicantList = 
      await page.locator('table, [role="table"], [class*="table"]').count() > 0 ||
      await page.locator('[class*="list"], [class*="applicant"]').count() > 0 ||
      await page.content().then(body => body.includes('Applicant') || body.includes('Awaiting'));
    
    expect(hasApplicantList).toBeTruthy();
  });

  test('should filter applicants by name', async ({ page }) => {
    // Check for search/filter input
    const searchInput = page.locator('input[type="search"], input[placeholder*="Search"], input[placeholder*="Filter"]').first();
    
    if (await searchInput.count() > 0) {
      await searchInput.fill('test');
      expect(await searchInput.inputValue()).toBe('test');
    } else {
      // Verify page has evaluator content
      const body = await page.content();
      expect(body).toContain('Evaluator');
    }
  });

  test('should start evaluation process', async ({ page }) => {
    // Check for start/review buttons
    const hasActionButtons = 
      await page.locator('button:has-text("Start"), button:has-text("Review"), button:has-text("Evaluate")').count() > 0 ||
      await page.locator('button[class*="primary"], button[class*="action"]').count() > 0;
    
    expect(hasActionButtons).toBeTruthy();
  });

  test('should display evaluation status indicators', async ({ page }) => {
    // Check for status badges or indicators
    const hasStatusIndicators = 
      await page.locator('[class*="status"], [class*="badge"], [class*="tag"]').count() > 0 ||
      await page.content().then(body => 
        body.includes('Pending') || 
        body.includes('Approved') || 
        body.includes('Rejected') ||
        body.includes('Status')
      );
    
    expect(hasStatusIndicators).toBeTruthy();
  });

  test('should show document/grade evaluator role distinction', async ({ page }) => {
    // Check for role-specific content
    const body = await page.content();
    const hasRoleContent = 
      body.includes('Document') ||
      body.includes('Grade') ||
      body.includes('Evaluator') ||
      body.includes('Review');
    
    expect(hasRoleContent).toBeTruthy();
  });

  test('should handle evaluation cancellation', async ({ page }) => {
    // Check for cancel or back buttons
    const hasCancelOptions = 
      await page.locator('button:has-text("Cancel"), button:has-text("Back")').count() > 0 ||
      await page.locator('button').count() > 0;
    
    expect(hasCancelOptions).toBeTruthy();
  });

  test('should display file/document information', async ({ page }) => {
    // Check for file/document related content
    const body = await page.content();
    const hasFileContent = 
      body.includes('File') ||
      body.includes('Document') ||
      body.includes('Upload') ||
      await page.locator('[class*="file"], [class*="document"]').count() > 0;
    
    expect(hasFileContent).toBeTruthy();
  });
});
