import { test, expect } from '@playwright/test';

test.describe('Admin Users Management Tests', () => {
  const BASE_URL = 'http://127.0.0.1:8000';
  
  test.beforeEach(async ({ page }) => {
    // Login as admin
    await page.goto(`${BASE_URL}/admin/login`);
    
    const emailInput = page.locator('input[type="email"], input[name="email"]').first();
    const passwordInput = page.locator('input[type="password"], input[name="password"]').first();
    
    await emailInput.fill('admin@eye-training.com');
    await passwordInput.fill('password');
    
    const submitButton = page.locator('button[type="submit"], input[type="submit"], button:has-text("ログイン"), button:has-text("Login")').first();
    await submitButton.click();
    
    await page.waitForURL(`${BASE_URL}/admin*`, { timeout: 15000 });
  });
  
  test('Users page loads without errors', async ({ page }) => {
    // Navigate to users page
    await page.goto(`${BASE_URL}/admin/users`);
    
    // Check for any JavaScript errors
    let jsErrors = [];
    page.on('pageerror', (error) => {
      jsErrors.push(error.message);
    });
    
    await page.waitForLoadState('networkidle');
    
    // Should not have critical errors
    expect(jsErrors.filter(error => 
      error.includes('TypeError') || 
      error.includes('ReferenceError') ||
      error.includes('Malformed UTF-8')
    )).toHaveLength(0);
    
    // Check that page title is not an error page
    await expect(page).not.toHaveTitle(/Error|エラー|500|404/);
  });
  
  test('Can access user creation form', async ({ page }) => {
    await page.goto(`${BASE_URL}/admin/users`);
    
    // Wait for page to load
    await page.waitForLoadState('networkidle');
    
    // Look for create/new user button
    const createButton = page.locator('a:has-text("新規"), a:has-text("作成"), a:has-text("Create"), a:has-text("New")').first();
    
    if (await createButton.isVisible()) {
      await createButton.click();
      await page.waitForLoadState('networkidle');
      
      // Should be on create user page
      expect(page.url()).toContain('/create');
    }
  });
  
  test('User table displays correctly with UTF-8 handling', async ({ page }) => {
    await page.goto(`${BASE_URL}/admin/users`);
    
    await page.waitForLoadState('networkidle');
    
    // Check for table or list view
    const tableExists = await page.locator('table, .filament-table, [role="table"]').first().isVisible({ timeout: 5000 }).catch(() => false);
    
    if (tableExists) {
      const pageContent = await page.content();
      
      // Should not contain encoding error indicators
      expect(pageContent).not.toContain('?????');
      expect(pageContent).not.toContain('\\u');
      expect(pageContent).not.toContain('Malformed UTF-8');
    }
  });
});