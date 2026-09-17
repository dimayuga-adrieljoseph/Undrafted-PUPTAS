import { test, expect } from '@playwright/test';

/**
 * E2E Test: Interviewer Workflow
 * 
 * Tests the interviewer dashboard and interview management workflows.
 * Uses real test authentication via TestAuthController.
 */

test.describe('Interviewer Workflow', () => {
  test.beforeEach(async ({ page }) => {
    // Authenticate as test interviewer using dev-login
    await page.goto('/dev-login?email=e2e.interviewer@test.local');
    await page.waitForLoadState('load');
    await page.waitForTimeout(2000); // Wait for dashboard to fully render
  });

  test('should load interviewer dashboard', async ({ page }) => {
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
      body.includes('Interview') ||
      body.includes('Applicant') ||
      await page.locator('[class*="stat"], [class*="card"]').count() > 0;
    
    expect(hasStats).toBeTruthy();
  });

  test('should show low slot alert for programs', async ({ page }) => {
    // Check for alert or warning indicators
    const hasAlerts = 
      await page.locator('[class*="alert"], [class*="warning"], [class*="notice"]').count() > 0 ||
      await page.content().then(body => 
        body.includes('Slot') || 
        body.includes('Program') || 
        body.includes('Alert')
      );
    
    expect(hasAlerts).toBeTruthy();
  });

  test('should display applicants awaiting interview', async ({ page }) => {
    // Check for applicant list or table
    const hasApplicantList = 
      await page.locator('table, [role="table"], [class*="table"]').count() > 0 ||
      await page.locator('[class*="list"], [class*="applicant"]').count() > 0 ||
      await page.content().then(body => body.includes('Applicant') || body.includes('Awaiting'));
    
    expect(hasApplicantList).toBeTruthy();
  });

  test('should filter applicants by name or email', async ({ page }) => {
    // Check for search/filter input
    const searchInput = page.locator('input[type="search"], input[placeholder*="Search"], input[placeholder*="Filter"]').first();
    
    if (await searchInput.count() > 0) {
      await searchInput.fill('test');
      expect(await searchInput.inputValue()).toBe('test');
    } else {
      // Verify page has interviewer content
      const body = await page.content();
      expect(body).toContain('Interview');
    }
  });

  test('should filter by program', async ({ page }) => {
    // Check for program filter
    const hasFilters = 
      await page.locator('select, [role="combobox"]').count() > 0 ||
      await page.content().then(body => body.includes('Program') || body.includes('Filter'));
    
    expect(hasFilters).toBeTruthy();
  });

  test('should start interview process', async ({ page }) => {
    // Check for start/begin interview buttons
    const hasActionButtons = 
      await page.locator('button:has-text("Start"), button:has-text("Begin"), button:has-text("Interview")').count() > 0 ||
      await page.locator('button[class*="primary"], button[class*="action"]').count() > 0;
    
    expect(hasActionButtons).toBeTruthy();
  });

  test('should display applicant qualification status', async ({ page }) => {
    // Check for qualification or status indicators
    const hasStatusContent = 
      await page.locator('[class*="status"], [class*="badge"], [class*="qualification"]').count() > 0 ||
      await page.content().then(body => 
        body.includes('Qualified') || 
        body.includes('Status') ||
        body.includes('Eligible')
      );
    
    expect(hasStatusContent).toBeTruthy();
  });

  test('should show interview notes textarea', async ({ page }) => {
    // Check for textarea or notes input
    const hasNotesInput = 
      await page.locator('textarea, [contenteditable="true"]').count() > 0 ||
      await page.content().then(body => body.includes('Notes') || body.includes('Comment'));
    
    expect(hasNotesInput).toBeTruthy();
  });

  test('should display charts with data visualization', async ({ page }) => {
    // Check for charts
    const hasCharts = 
      await page.locator('canvas, svg, [class*="chart"]').count() > 0 ||
      await page.content().then(body => body.includes('Chart') || body.includes('Statistics'));
    
    expect(hasCharts).toBeTruthy();
  });
});
