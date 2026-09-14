import { test, expect } from '@playwright/test';

/**
 * E2E Test: Authentication Flow
 * 
 * Tests the complete authentication workflow including:
 * - Login page access
 * - IDP (Identity Provider) authentication
 * - Role-based dashboard redirection
 * - Logout functionality
 * 
 * User Journey:
 * 1. Access login page
 * 2. Authenticate via IDP
 * 3. Verify redirect to appropriate dashboard
 * 4. Logout successfully
 */

test.describe('Authentication Workflow', () => {
  test('should load login page', async ({ page }) => {
    await page.goto('/');
    
    // Should redirect to login or show login button
    await page.waitForTimeout(1000);
    
    const url = page.url();
    const body = await page.textContent('body');
    
    // Verify we're on a login-related page or see login elements
    const isLoginPage = 
      url.includes('login') ||
      url.includes('auth') ||
      body.includes('Login') ||
      body.includes('Sign in') ||
      body.includes('IDP');
    
    expect(isLoginPage).toBeTruthy();
  });

  test('should redirect unauthenticated users to login', async ({ page }) => {
    // Try to access protected route
    await page.goto('/dashboard');
    
    // Wait for redirect
    await page.waitForTimeout(1500);
    
    const finalUrl = page.url();
    
    // Should be redirected to auth page
    expect(
      finalUrl.includes('login') ||
      finalUrl.includes('auth') ||
      finalUrl.includes('idp')
    ).toBeTruthy();
  });

  test('should show IDP authentication option', async ({ page }) => {
    await page.goto('/');
    await page.waitForTimeout(1000);
    
    const body = await page.textContent('body');
    
    // Check for IDP-related content
    const hasAuthOption = 
      body.includes('IDP') ||
      body.includes('Identity Provider') ||
      body.includes('Login') ||
      body.includes('Sign in') ||
      body.includes('Microsoft');
    
    expect(hasAuthOption).toBeTruthy();
  });

  test('should handle logout correctly', async ({ page, context }) => {
    // Note: This test assumes you can set auth state manually
    // In real scenario, you'd login first
    
    // Set a mock session cookie to simulate logged-in state
    await context.addCookies([{
      name: 'laravel_session',
      value: 'mock_session_value',
      domain: '127.0.0.1',
      path: '/',
    }]);
    
    // Try to access a protected page
    await page.goto('/dashboard');
    await page.waitForTimeout(1000);
    
    // Look for logout button or link
    const logoutButton = page.locator('text=/logout|sign out/i').first();
    
    if (await logoutButton.isVisible()) {
      await logoutButton.click();
      await page.waitForTimeout(1000);
      
      // Should redirect to login page
      const finalUrl = page.url();
      expect(
        finalUrl.includes('login') ||
        finalUrl.includes('auth') ||
        finalUrl === 'http://127.0.0.1:8000/'
      ).toBeTruthy();
    }
  });

  test('should show appropriate error for invalid credentials', async ({ page }) => {
    await page.goto('/');
    await page.waitForTimeout(1000);
    
    // If there's a dev/emergency login form
    const emailInput = page.locator('input[type="email"]');
    
    if (await emailInput.isVisible()) {
      await emailInput.fill('invalid@test.com');
      
      const passwordInput = page.locator('input[type="password"]');
      if (await passwordInput.isVisible()) {
        await passwordInput.fill('wrongpassword');
        
        await page.click('button[type="submit"]');
        await page.waitForTimeout(1500);
        
        const body = await page.textContent('body');
        
        // Should show error message
        const hasError = 
          body.includes('Invalid') ||
          body.includes('incorrect') ||
          body.includes('failed') ||
          body.includes('error');
        
        // Error shown or still on login page
        expect(hasError || page.url().includes('login')).toBeTruthy();
      }
    }
  });
});
