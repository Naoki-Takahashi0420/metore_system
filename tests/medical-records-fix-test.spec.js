import { test, expect } from '@playwright/test';

test.describe('Medical Records Fix Test', () => {
  const BASE_URL = 'http://127.0.0.1:8000';
  
  async function adminLogin(page) {
    await page.goto(`${BASE_URL}/admin/login`);
    await page.waitForLoadState('networkidle');
    
    const emailInput = page.locator('input[type="email"], input[name="email"]').first();
    const passwordInput = page.locator('input[type="password"], input[name="password"]').first();
    
    await emailInput.fill('admin@eye-training.com');
    await passwordInput.fill('password');
    
    const submitButton = page.locator('button[type="submit"], input[type="submit"], button:has-text("ログイン"), button:has-text("Login")').first();
    await submitButton.click();
    
    await page.waitForTimeout(3000);
  }

  test('Medical records page loads without 500 error', async ({ page }) => {
    await adminLogin(page);
    
    // Navigate to medical records
    const response = await page.goto(`${BASE_URL}/admin/medical-records`);
    await page.waitForLoadState('networkidle');
    
    const status = response?.status() || 0;
    console.log(`Medical records page status: ${status}`);
    
    // Should not be 500 error
    expect(status).not.toBe(500);
    
    // Check page content for errors
    const pageContent = await page.content();
    expect(pageContent).not.toContain('TypeError');
    expect(pageContent).not.toContain('Malformed UTF-8');
    expect(pageContent).not.toContain('JsonException');
  });

  test('Medical records create form loads', async ({ page }) => {
    await adminLogin(page);
    
    // Try to access create form
    const response = await page.goto(`${BASE_URL}/admin/medical-records/create`);
    await page.waitForLoadState('networkidle');
    
    const status = response?.status() || 0;
    console.log(`Medical records create page status: ${status}`);
    
    // Should not be 500 error
    expect(status).not.toBe(500);
    
    // Check for form elements (if accessible)
    const pageContent = await page.content();
    expect(pageContent).not.toContain('TypeError');
    expect(pageContent).not.toContain('isOptionDisabled');
  });

  test('Users page loads without 500 error', async ({ page }) => {
    await adminLogin(page);
    
    // Navigate to users page
    const response = await page.goto(`${BASE_URL}/admin/users`);
    await page.waitForLoadState('networkidle');
    
    const status = response?.status() || 0;
    console.log(`Users page status: ${status}`);
    
    // Should not be 500 error
    expect(status).not.toBe(500);
    
    // Check page content for errors
    const pageContent = await page.content();
    expect(pageContent).not.toContain('TypeError');
    expect(pageContent).not.toContain('Malformed UTF-8');
    expect(pageContent).not.toContain('JsonException');
  });
});