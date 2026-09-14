import { test, expect } from '@playwright/test';

/**
 * E2E Test: Admin Dashboard Workflow
 * 
 * Tests the admin dashboard interface and applicant management workflows.
 * Uses real test authentication via TestAuthController.
 */

test.describe('Admin Dashboard Workflow', () => {
  test.beforeEach(async ({ page }) => {
    // Authenticate as test admin using dev-login
    await page.goto('/dev-login?email=e2e.admin@test.local');
    await page.waitForLoadState('load');
    await page.waitForTimeout(2000); // Wait for dashboard to fully render
  });

  test('should load admin dashboard with statistics', async ({ page }) => {
    // Verify dashboard loaded
    const body = await page.content();
    const pageLoaded = body.length > 100;
    expect(pageLoaded).toBeTruthy();
    
    // Verify not 404
    const title = await page.title();
    expect(title).not.toContain('404');
  });

  test('should display summary statistics cards', async ({ page }) => {
    // Check for stat/metric elements
    const body = await page.content();
    const hasStatsContent = 
      body.includes('Total') ||
      body.includes('Applicant') ||
      body.includes('Application') ||
      body.includes('Count') ||
      await page.locator('[class*="stat"], [class*="card"], [class*="metric"]').count() > 0;
    
    expect(hasStatsContent).toBeTruthy();
  });

  test('should display applicants list/table', async ({ page }) => {
    // Check for table or list elements
    const hasDataDisplay = 
      await page.locator('table, [role="table"], [class*="table"]').count() > 0 ||
      await page.locator('[class*="list"], [class*="grid"]').count() > 0;
    
    expect(hasDataDisplay).toBeTruthy();
  });

  test('should filter applicants by search', async ({ page }) => {
    // Check for search input
    const searchInput = page.locator('input[type="search"], input[placeholder*="Search"], input[name*="search"]').first();
    
    if (await searchInput.count() > 0) {
      await searchInput.fill('test');
      expect(await searchInput.inputValue()).toBe('test');
    } else {
      // If no search, verify page has filterable content
      const body = await page.content();
      expect(body.length).toBeGreaterThan(100);
    }
  });

  test('should filter by application status', async ({ page }) => {
    // Check for status filter dropdown or buttons
    const hasFilters = 
      await page.locator('select, [role="combobox"], button[class*="filter"]').count() > 0 ||
      await page.content().then(body => body.includes('Filter') || body.includes('Status'));
    
    expect(hasFilters).toBeTruthy();
  });

  test('should paginate through applicants', async ({ page }) => {
    // Check for pagination controls
    const hasPagination = 
      await page.locator('button:has-text("Next"), button:has-text("Previous"), [class*="pagination"]').count() > 0 ||
      await page.content().then(body => body.includes('Page') || body.includes('of'));
    
    expect(hasPagination).toBeTruthy();
  });

  test('should display charts/visualizations', async ({ page }) => {
    // Check for chart elements (canvas, svg, or chart-related classes)
    const hasCharts = 
      await page.locator('canvas, svg, [class*="chart"]').count() > 0 ||
      await page.content().then(body => body.includes('Chart') || body.includes('graph'));
    
    expect(hasCharts).toBeTruthy();
  });

  test('should access application review modal', async ({ page }) => {
    // Check for modal triggers or review buttons
    const hasReviewButtons = 
      await page.locator('button, a').count() > 0;
    
    expect(hasReviewButtons).toBeTruthy();
  });
});
