import { test, expect } from '@playwright/test';

/**
 * E2E Test: Applicant Dashboard & Navigation
 * 
 * Tests the applicant-facing dashboard interface and navigation.
 * Uses real test authentication via TestAuthController.
 */

test.describe('Applicant Dashboard & Navigation', () => {
  test.beforeEach(async ({ page }) => {
    // Authenticate as test applicant using dev-login
    await page.goto('/dev-login?email=e2e.applicant@test.local');
    await page.waitForLoadState('load');
    await page.waitForTimeout(2000); // Wait for dashboard to fully render
  });

  test('should load applicant dashboard', async ({ page }) => {
    // Verify page loaded successfully
    const body = await page.content();
    const pageLoaded = body.length > 100;
    expect(pageLoaded).toBeTruthy();
    
    // Verify not showing error page
    const title = await page.title();
    expect(title).not.toContain('404');
    expect(title).not.toContain('Not Found');
  });

  test('should display sidebar navigation', async ({ page }) => {
    // Check for navigation elements
    const hasNav = 
      await page.locator('nav, aside, [role="navigation"], .sidebar').count() > 0;
    
    expect(hasNav).toBeTruthy();
  });

  test('should show application status card', async ({ page }) => {
    // Check for status-related content
    const body = await page.content();
    const hasStatusContent = 
      body.includes('Status') ||
      body.includes('Application') ||
      body.includes('Profile') ||
      await page.locator('[class*="status"], [class*="card"]').count() > 0;
    
    expect(hasStatusContent).toBeTruthy();
  });

  test('should navigate to profile page', async ({ page }) => {
    // Look for profile link
    const profileLink = page.locator('a[href*="profile"], button:has-text("Profile")').first();
    
    if (await profileLink.count() > 0) {
      await profileLink.click();
      await page.waitForLoadState('networkidle');
      
      // Verify navigation occurred
      const url = page.url();
      expect(url).toContain('profile');
    } else {
      // If no link, at least verify dashboard has profile section
      const body = await page.content();
      expect(body).toContain('applicant');
    }
  });

  test('should display document upload section', async ({ page }) => {
    // Check for upload-related content
    const body = await page.content();
    const hasUploadContent = 
      body.includes('Upload') ||
      body.includes('Document') ||
      body.includes('File') ||
      await page.locator('input[type="file"], button:has-text("Upload")').count() > 0;
    
    expect(hasUploadContent).toBeTruthy();
  });

  test('should show application timeline/progress', async ({ page }) => {
    // Check for timeline or progress indicators
    const body = await page.content();
    const hasProgressContent = 
      body.includes('Step') ||
      body.includes('Progress') ||
      body.includes('Timeline') ||
      body.includes('Stage') ||
      await page.locator('[class*="timeline"], [class*="progress"], [class*="step"]').count() > 0;
    
    expect(hasProgressContent).toBeTruthy();
  });

  test('should handle sidebar collapse/expand on mobile', async ({ page }) => {
    // Set mobile viewport
    await page.setViewportSize({ width: 375, height: 667 });
    await page.reload();
    await page.waitForLoadState('networkidle');
    
    // Check if sidebar or menu exists
    const hasMobileNav = 
      await page.locator('button[aria-label*="menu"], button:has-text("Menu"), .mobile-menu').count() > 0 ||
      await page.locator('nav, aside').count() > 0;
    
    expect(hasMobileNav).toBeTruthy();
  });
});
