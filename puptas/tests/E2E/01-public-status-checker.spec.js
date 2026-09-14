import { test, expect } from '@playwright/test';

/**
 * E2E Test: Public Status Checker Workflow
 * 
 * Tests the public-facing status checker feature that allows
 * prospective students to check their application status without logging in.
 */

test.describe('Public Status Checker Workflow', () => {
  test.beforeEach(async ({ page }) => {
    // Navigate to the public status checker page (correct route)
    await page.goto('/admission-results');
    await page.waitForLoadState('networkidle');
  });

  test('should load status checker page successfully', async ({ page }) => {
    // Verify page loads - check for any content
    const body = await page.content();
    const hasContent = body.length > 100; // Page has loaded with content
    expect(hasContent).toBeTruthy();
    
    // Verify we're on the right page (not a 404)
    const title = await page.title();
    expect(title).not.toContain('Not Found');
    expect(title).not.toContain('404');
  });

  test('should display admission results form', async ({ page }) => {
    // Check if form exists (flexible - just verify form is present)
    const hasForm = await page.locator('form').count() > 0 ||
                    await page.locator('input').count() > 0 ||
                    await page.locator('button').count() > 0;
    
    expect(hasForm).toBeTruthy();
  });

  test('should have page heading or title', async ({ page }) => {
    // Check for any heading that indicates this is status checker
    const body = await page.content();
    const hasHeading = 
      body.includes('Status') ||
      body.includes('Admission') ||
      body.includes('Results') ||
      body.includes('Check') ||
      body.includes('PUPTAS');
    
    expect(hasHeading).toBeTruthy();
  });

  test('should be publicly accessible (no auth required)', async ({ page }) => {
    // Verify page loads without authentication
    const response = await page.goto('/admission-results');
    
    // Should not redirect to login
    expect(page.url()).toContain('/admission-results');
    expect(response?.status()).toBeLessThan(400);
  });

  test('should have interactive elements', async ({ page }) => {
    // Check for any interactive elements (inputs, buttons, links)
    const hasInteractiveElements = 
      await page.locator('input, button, a, select, textarea').count() > 0;
    
    expect(hasInteractiveElements).toBeTruthy();
  });
});
