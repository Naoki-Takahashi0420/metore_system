import { test, expect } from '@playwright/test';

test.describe('Basic System Functionality Tests', () => {
  const BASE_URL = 'http://127.0.0.1:8000';
  
  test('Public reservation page loads without errors', async ({ page }) => {
    await page.goto(`${BASE_URL}/`);
    
    // Check that the page loads
    await expect(page).not.toHaveTitle(/Error/);
    
    // Check for any JavaScript errors
    let jsErrors = [];
    page.on('pageerror', (error) => {
      jsErrors.push(error.message);
    });
    
    await page.waitForLoadState('networkidle');
    
    if (jsErrors.length > 0) {
      console.log('JavaScript errors found:', jsErrors);
    }
    
    // Should not have critical JavaScript errors
    expect(jsErrors.filter(error => error.includes('ReferenceError') || error.includes('TypeError'))).toHaveLength(0);
  });
  
  test('Admin login page displays correctly', async ({ page }) => {
    await page.goto(`${BASE_URL}/admin/login`);
    
    // Check page title
    await expect(page).toHaveTitle(/ログイン|Login/);
    
    // Check for form elements (with more flexible selectors)
    const emailInput = page.locator('input[type="email"], input[name="email"], input[placeholder*="メール"], input[placeholder*="mail"]').first();
    const passwordInput = page.locator('input[type="password"], input[name="password"], input[placeholder*="パスワード"], input[placeholder*="password"]').first();
    
    await expect(emailInput).toBeVisible({ timeout: 10000 });
    await expect(passwordInput).toBeVisible({ timeout: 10000 });
  });
  
  test('Admin login works with valid credentials', async ({ page }) => {
    await page.goto(`${BASE_URL}/admin/login`);
    
    // Wait for page to load
    await page.waitForLoadState('networkidle');
    
    // Find and fill login form
    const emailInput = page.locator('input[type="email"], input[name="email"]').first();
    const passwordInput = page.locator('input[type="password"], input[name="password"]').first();
    
    await emailInput.fill('admin@eye-training.com');
    await passwordInput.fill('password');
    
    // Submit form
    const submitButton = page.locator('button[type="submit"], input[type="submit"], button:has-text("ログイン"), button:has-text("Login")').first();
    await submitButton.click();
    
    // Should redirect to dashboard
    await page.waitForURL(`${BASE_URL}/admin*`, { timeout: 15000 });
    
    // Check we're on admin dashboard
    expect(page.url()).toContain('/admin');
  });
  
  test('System handles UTF-8 characters correctly', async ({ page }) => {
    await page.goto(`${BASE_URL}/admin/login`);
    
    // Check that Japanese characters display correctly
    const pageContent = await page.content();
    
    // Should contain Japanese text without encoding issues
    expect(pageContent).toContain('ログイン');
    expect(pageContent).not.toContain('\\u');
    expect(pageContent).not.toContain('?????');
  });
});