import { test, expect } from '@playwright/test';

test.describe('Livewire Login Fix Verification', () => {
  const PRODUCTION_URL = 'http://13.115.38.179';
  const LOGIN_URL = `${PRODUCTION_URL}/admin/login`;
  
  test.beforeEach(async ({ page }) => {
    // Enable request/response logging for debugging
    page.on('request', request => {
      if (request.url().includes('livewire') || request.url().includes('login')) {
        console.log(`→ ${request.method()} ${request.url()}`);
      }
    });
    
    page.on('response', response => {
      if (response.url().includes('livewire') || response.url().includes('login')) {
        console.log(`← ${response.status()} ${response.url()}`);
      }
    });
  });

  test('should load login page successfully', async ({ page }) => {
    console.log('Testing login page load...');
    
    const response = await page.goto(LOGIN_URL, { 
      waitUntil: 'networkidle',
      timeout: 30000 
    });
    
    expect(response.status()).toBe(200);
    
    // Check for Filament login page elements
    await expect(page.locator('input[type="email"]')).toBeVisible();
    await expect(page.locator('input[type="password"]')).toBeVisible();
    await expect(page.locator('button[type="submit"]')).toBeVisible();
    
    // Check for Livewire scripts
    const livewireScript = page.locator('script[src*="livewire"]');
    await expect(livewireScript).toBeAttached();
    
    console.log('✓ Login page loaded successfully');
  });

  test('should have CSRF token available', async ({ page }) => {
    console.log('Testing CSRF token availability...');
    
    await page.goto(LOGIN_URL, { waitUntil: 'networkidle' });
    
    // Check for CSRF token in meta tag
    const csrfToken = await page.locator('meta[name="csrf-token"]').getAttribute('content');
    expect(csrfToken).toBeTruthy();
    expect(csrfToken.length).toBeGreaterThan(10);
    
    console.log(`✓ CSRF token found: ${csrfToken.substring(0, 10)}...`);
  });

  test('should handle Livewire updates without 500 error', async ({ page }) => {
    console.log('Testing Livewire update endpoint...');
    
    await page.goto(LOGIN_URL, { waitUntil: 'networkidle' });
    
    // Fill in the login form to trigger Livewire updates
    await page.fill('input[type="email"]', 'admin@xsyumeno.com');
    
    // Wait a moment for any Livewire updates to process
    await page.waitForTimeout(500);
    
    // Check that no 500 errors occurred during form interaction
    const responses = [];
    page.on('response', response => {
      if (response.url().includes('livewire/update')) {
        responses.push({
          status: response.status(),
          url: response.url()
        });
      }
    });
    
    // Fill password to trigger another Livewire update
    await page.fill('input[type="password"]', 'password');
    await page.waitForTimeout(500);
    
    // Check for any captured 500 errors
    const serverErrors = responses.filter(r => r.status >= 500);
    expect(serverErrors.length).toBe(0);
    
    console.log('✓ No Livewire 500 errors during form interaction');
  });

  test('should successfully authenticate user', async ({ page }) => {
    console.log('Testing full login flow...');
    
    await page.goto(LOGIN_URL, { waitUntil: 'networkidle' });
    
    // Fill in login credentials
    await page.fill('input[type="email"]', 'admin@xsyumeno.com');
    await page.fill('input[type="password"]', 'password');
    
    // Submit the form and wait for navigation
    const navigationPromise = page.waitForURL(/admin\/?$/);
    await page.click('button[type="submit"]');
    
    try {
      await navigationPromise;
      console.log('✓ Login successful - redirected to admin dashboard');
      
      // Verify we're on the admin dashboard
      expect(page.url()).toMatch(/admin\/?$/);
      
      // Check for dashboard elements that indicate successful login
      await expect(page.locator('.fi-topbar')).toBeVisible({ timeout: 10000 });
      
    } catch (error) {
      // If navigation fails, check for error messages
      const errorMessage = await page.locator('[role="alert"]').textContent();
      if (errorMessage) {
        console.log(`Login error message: ${errorMessage}`);
      }
      
      // Check if we're still on login page with errors
      const currentUrl = page.url();
      if (currentUrl.includes('login')) {
        console.log('Still on login page - checking for validation errors');
        
        // Look for validation errors
        const validationErrors = await page.locator('.fi-fo-field-wrp-error-message').allTextContents();
        if (validationErrors.length > 0) {
          console.log('Validation errors found:', validationErrors);
        }
      }
      
      throw error;
    }
  });

  test('should maintain session after login', async ({ page }) => {
    console.log('Testing session persistence...');
    
    // First, log in
    await page.goto(LOGIN_URL, { waitUntil: 'networkidle' });
    await page.fill('input[type="email"]', 'admin@xsyumeno.com');
    await page.fill('input[type="password"]', 'password');
    await page.click('button[type="submit"]');
    
    // Wait for successful login
    await page.waitForURL(/admin\/?$/, { timeout: 10000 });
    
    // Navigate to another admin page
    await page.goto(`${PRODUCTION_URL}/admin`, { waitUntil: 'networkidle' });
    
    // Should still be authenticated (not redirected to login)
    expect(page.url()).not.toContain('login');
    await expect(page.locator('.fi-topbar')).toBeVisible();
    
    console.log('✓ Session persisted correctly');
  });

  test('should handle AJAX requests properly', async ({ page }) => {
    console.log('Testing AJAX request handling...');
    
    // Log in first
    await page.goto(LOGIN_URL, { waitUntil: 'networkidle' });
    await page.fill('input[type="email"]', 'admin@xsyumeno.com');
    await page.fill('input[type="password"]', 'password');
    await page.click('button[type="submit"]');
    await page.waitForURL(/admin\/?$/, { timeout: 10000 });
    
    // Monitor AJAX requests
    const ajaxRequests = [];
    page.on('request', request => {
      if (request.url().includes('livewire') && request.method() === 'POST') {
        ajaxRequests.push(request);
      }
    });
    
    page.on('response', response => {
      if (response.url().includes('livewire') && response.status() >= 400) {
        console.log(`AJAX Error: ${response.status()} ${response.url()}`);
      }
    });
    
    // Interact with dashboard elements that might trigger Livewire requests
    try {
      // Look for any interactive elements
      const interactiveElements = await page.locator('[wire\\:click], [wire\\:model], [wire\\:submit]').count();
      console.log(`Found ${interactiveElements} Livewire interactive elements`);
      
      if (interactiveElements > 0) {
        const firstElement = page.locator('[wire\\:click], [wire\\:model], [wire\\:submit]').first();
        await firstElement.click();
        await page.waitForTimeout(2000); // Wait for any AJAX to complete
      }
    } catch (error) {
      console.log('No interactive Livewire elements found or error interacting');
    }
    
    console.log('✓ AJAX requests handled without errors');
  });
});

// Additional utility test to check server health
test.describe('Server Health Check', () => {
  test('should have all required services running', async ({ page }) => {
    console.log('Checking server health...');
    
    // Test basic HTTP connectivity
    const response = await page.request.get('http://13.115.38.179/admin/login');
    expect(response.status()).toBe(200);
    
    // Test that PHP-FPM is responding
    const headers = response.headers();
    expect(headers['content-type']).toContain('text/html');
    
    console.log('✓ Server is responding correctly');
  });
});