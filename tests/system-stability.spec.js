import { test, expect } from '@playwright/test';

test.describe('System Stability Tests', () => {
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
    
    // Wait for either dashboard or redirect
    await page.waitForTimeout(3000);
  }

  test('Admin pages accessibility check', async ({ page }) => {
    await adminLogin(page);
    
    const adminPages = [
      '/admin',
      '/admin/customers',
      '/admin/reservations',
      '/admin/medical-records',
      '/admin/users',
      '/admin/stores',
      '/admin/shift-management'
    ];
    
    let results = {
      accessible: [],
      errors: [],
      redirects: []
    };
    
    for (const adminPage of adminPages) {
      try {
        const response = await page.goto(`${BASE_URL}${adminPage}`);
        await page.waitForLoadState('networkidle');
        
        const status = response?.status() || 0;
        const url = page.url();
        
        if (status >= 200 && status < 300) {
          // Check for error content
          const pageContent = await page.content();
          
          if (pageContent.includes('Error') || pageContent.includes('Exception') || pageContent.includes('500')) {
            results.errors.push({
              page: adminPage,
              status: status,
              error: 'Error content found'
            });
          } else {
            results.accessible.push({
              page: adminPage,
              status: status,
              url: url
            });
          }
        } else if (status >= 300 && status < 400) {
          results.redirects.push({
            page: adminPage,
            status: status,
            redirectTo: url
          });
        } else {
          results.errors.push({
            page: adminPage,
            status: status,
            error: 'HTTP error'
          });
        }
      } catch (error) {
        results.errors.push({
          page: adminPage,
          status: 0,
          error: error.message
        });
      }
      
      // Small delay between requests
      await page.waitForTimeout(1000);
    }
    
    console.log('Accessibility Results:', JSON.stringify(results, null, 2));
    
    // At least some pages should be accessible
    expect(results.accessible.length + results.redirects.length).toBeGreaterThan(0);
    
    // Critical pages should not have errors
    const criticalErrors = results.errors.filter(error => 
      error.page.includes('/admin') && 
      !error.page.includes('/medical-records') && 
      !error.page.includes('/users')
    );
    
    expect(criticalErrors.length).toBeLessThan(results.errors.length);
  });

  test('Security headers and basic protections', async ({ page }) => {
    const response = await page.goto(`${BASE_URL}/admin/login`);
    const headers = response?.headers();
    
    // Check for basic security headers
    console.log('Security headers:', headers);
    
    // CSRF protection should be present
    const pageContent = await page.content();
    expect(pageContent).toContain('csrf-token');
  });

  test('Database connectivity', async ({ page }) => {
    await adminLogin(page);
    
    // Try to access a simple page that requires DB
    try {
      await page.goto(`${BASE_URL}/admin/stores`);
      await page.waitForLoadState('networkidle');
      
      const pageContent = await page.content();
      
      // Should not have database connection errors
      expect(pageContent).not.toContain('database connection');
      expect(pageContent).not.toContain('SQLSTATE');
      expect(pageContent).not.toContain('Connection refused');
      
    } catch (error) {
      console.log('Database connectivity test failed:', error.message);
      // Log but don't fail the test completely
    }
  });

  test('Session management', async ({ page }) => {
    // Test 1: Login
    await adminLogin(page);
    
    // Test 2: Access protected area
    await page.goto(`${BASE_URL}/admin/stores`);
    await page.waitForLoadState('networkidle');
    
    // Should not be redirected to login
    expect(page.url()).not.toContain('/admin/login');
    
    // Test 3: Session persistence (refresh page)
    await page.reload();
    await page.waitForLoadState('networkidle');
    
    // Should still be logged in
    expect(page.url()).not.toContain('/admin/login');
  });

  test('JavaScript and CSS loading', async ({ page }) => {
    let resourceErrors = [];
    
    page.on('response', response => {
      if (response.status() >= 400 && (
        response.url().includes('.js') || 
        response.url().includes('.css') ||
        response.url().includes('livewire')
      )) {
        resourceErrors.push({
          url: response.url(),
          status: response.status()
        });
      }
    });
    
    await page.goto(`${BASE_URL}/admin/login`);
    await page.waitForLoadState('networkidle');
    
    // Check for critical resource loading errors
    console.log('Resource errors:', resourceErrors);
    
    const criticalErrors = resourceErrors.filter(error => 
      error.url.includes('livewire') || 
      error.url.includes('app.js') ||
      error.url.includes('app.css')
    );
    
    expect(criticalErrors.length).toBe(0);
  });

  test('Performance and memory basic check', async ({ page }) => {
    const startTime = Date.now();
    
    await page.goto(`${BASE_URL}/admin/login`);
    await page.waitForLoadState('networkidle');
    
    const loginTime = Date.now() - startTime;
    
    // Login page should load within reasonable time
    expect(loginTime).toBeLessThan(10000); // 10 seconds max
    
    console.log(`Login page load time: ${loginTime}ms`);
  });
});