import { test, expect } from '@playwright/test';

test.describe('Edge Cases and Error Handling Tests', () => {
  const BASE_URL = 'http://127.0.0.1:8000';
  
  async function adminLogin(page) {
    await page.goto(`${BASE_URL}/admin/login`);
    
    const emailInput = page.locator('input[type="email"], input[name="email"]').first();
    const passwordInput = page.locator('input[type="password"], input[name="password"]').first();
    
    await emailInput.fill('admin@eye-training.com');
    await passwordInput.fill('password');
    
    const submitButton = page.locator('button[type="submit"], input[type="submit"], button:has-text("ログイン"), button:has-text("Login")').first();
    await submitButton.click();
    
    await page.waitForURL(`${BASE_URL}/admin*`, { timeout: 15000 });
  }

  test('Invalid login attempts', async ({ page }) => {
    await page.goto(`${BASE_URL}/admin/login`);
    
    // Test 1: Empty credentials
    const emailInput = page.locator('input[type="email"], input[name="email"]').first();
    const passwordInput = page.locator('input[type="password"], input[name="password"]').first();
    const submitButton = page.locator('button[type="submit"], input[type="submit"], button:has-text("ログイン"), button:has-text("Login")').first();
    
    await submitButton.click();
    await page.waitForLoadState('networkidle');
    
    // Should stay on login page
    expect(page.url()).toContain('/admin/login');
    
    // Test 2: Wrong email format
    await emailInput.fill('invalid-email');
    await passwordInput.fill('password');
    await submitButton.click();
    await page.waitForLoadState('networkidle');
    
    // Test 3: Wrong credentials
    await emailInput.fill('wrong@example.com');
    await passwordInput.fill('wrongpassword');
    await submitButton.click();
    await page.waitForLoadState('networkidle');
    
    // Should still be on login page
    expect(page.url()).toContain('/admin/login');
  });

  test('SQL Injection attempts in forms', async ({ page }) => {
    await adminLogin(page);
    
    // Test SQL injection in customer creation
    await page.goto(`${BASE_URL}/admin/customers`);
    await page.waitForLoadState('networkidle');
    
    const createButton = page.locator('a:has-text("新規"), a:has-text("作成"), a:has-text("Create")').first();
    if (await createButton.isVisible({ timeout: 5000 })) {
      await createButton.click();
      await page.waitForLoadState('networkidle');
      
      // Try SQL injection payloads
      const sqlPayloads = [
        "'; DROP TABLE customers; --",
        "' OR '1'='1",
        "'; UPDATE customers SET name='hacked'; --",
        "<script>alert('xss')</script>",
        "../../../../etc/passwd"
      ];
      
      for (const payload of sqlPayloads) {
        // Clear and fill with malicious payload
        const lastNameInput = page.locator('input[name="last_name"]').first();
        if (await lastNameInput.isVisible()) {
          await lastNameInput.clear();
          await lastNameInput.fill(payload);
          
          const firstNameInput = page.locator('input[name="first_name"]').first();
          await firstNameInput.fill('テスト');
          
          const phoneInput = page.locator('input[name="phone"]').first();
          await phoneInput.fill('090-1234-5678');
          
          // Submit and check for errors
          const saveButton = page.locator('button[type="submit"], button:has-text("保存")').first();
          await saveButton.click();
          await page.waitForLoadState('networkidle');
          
          // Should not crash or show SQL errors
          const pageContent = await page.content();
          expect(pageContent).not.toContain('SQL');
          expect(pageContent).not.toContain('mysql_error');
          expect(pageContent).not.toContain('PDOException');
        }
        
        // Navigate back to create form
        await page.goto(`${BASE_URL}/admin/customers/create`);
        await page.waitForLoadState('networkidle');
      }
    }
  });

  test('UTF-8 and special character handling', async ({ page }) => {
    await adminLogin(page);
    
    // Test with various UTF-8 characters
    const specialCharacters = [
      { lastName: '山田', firstName: '太郎', phone: '090-1111-1111' },
      { lastName: '佐藤🌸', firstName: '花子♥', phone: '090-2222-2222' },
      { lastName: 'Müller', firstName: 'François', phone: '090-3333-3333' },
      { lastName: '김철수', firstName: '이영희', phone: '090-4444-4444' },
      { lastName: '测试', firstName: '用户', phone: '090-5555-5555' }
    ];
    
    await page.goto(`${BASE_URL}/admin/customers`);
    await page.waitForLoadState('networkidle');
    
    const createButton = page.locator('a:has-text("新規"), a:has-text("作成"), a:has-text("Create")').first();
    if (await createButton.isVisible({ timeout: 5000 })) {
      for (const testData of specialCharacters) {
        await createButton.click();
        await page.waitForLoadState('networkidle');
        
        // Fill form with special characters
        await page.fill('input[name="last_name"]', testData.lastName);
        await page.fill('input[name="first_name"]', testData.firstName);
        await page.fill('input[name="last_name_kana"]', 'テスト');
        await page.fill('input[name="first_name_kana"]', 'ユーザー');
        await page.fill('input[name="phone"]', testData.phone);
        
        const saveButton = page.locator('button[type="submit"], button:has-text("保存")').first();
        await saveButton.click();
        await page.waitForLoadState('networkidle');
        
        // Check for encoding errors
        const pageContent = await page.content();
        expect(pageContent).not.toContain('?????');
        expect(pageContent).not.toContain('\\u');
        expect(pageContent).not.toContain('Malformed UTF-8');
        
        // Navigate back
        await page.goto(`${BASE_URL}/admin/customers`);
        await page.waitForLoadState('networkidle');
      }
    }
  });

  test('Large data handling and pagination', async ({ page }) => {
    await adminLogin(page);
    
    // Test pagination and large data sets
    await page.goto(`${BASE_URL}/admin/customers`);
    await page.waitForLoadState('networkidle');
    
    // Check if pagination controls exist
    const paginationNext = page.locator('a:has-text("次"), a:has-text("Next"), button:has-text("次"), button:has-text("Next")').first();
    const paginationPrev = page.locator('a:has-text("前"), a:has-text("Previous"), button:has-text("前"), button:has-text("Previous")').first();
    
    if (await paginationNext.isVisible({ timeout: 5000 })) {
      await paginationNext.click();
      await page.waitForLoadState('networkidle');
      
      // Should not crash
      const pageContent = await page.content();
      expect(pageContent).not.toContain('Error');
      expect(pageContent).not.toContain('500');
    }
    
    // Test search with large results
    const searchInput = page.locator('input[type="search"], input[placeholder*="検索"]').first();
    if (await searchInput.isVisible({ timeout: 5000 })) {
      // Search for common character that might return many results
      await searchInput.fill('田');
      await page.waitForLoadState('networkidle');
      
      // Page should still be responsive
      const tableExists = await page.locator('table, .filament-table').first().isVisible({ timeout: 10000 });
      if (tableExists) {
        const tableContent = await page.locator('table, .filament-table').first().textContent();
        expect(tableContent).not.toContain('Error');
      }
    }
  });

  test('Concurrent access and session handling', async ({ browser }) => {
    // Create multiple contexts to simulate concurrent users
    const context1 = await browser.newContext();
    const context2 = await browser.newContext();
    const page1 = await context1.newPage();
    const page2 = await context2.newPage();
    
    try {
      // Both users try to login simultaneously
      await Promise.all([
        adminLogin(page1),
        adminLogin(page2)
      ]);
      
      // Both should be able to access admin areas
      await Promise.all([
        page1.goto(`${BASE_URL}/admin/customers`),
        page2.goto(`${BASE_URL}/admin/reservations`)
      ]);
      
      await Promise.all([
        page1.waitForLoadState('networkidle'),
        page2.waitForLoadState('networkidle')
      ]);
      
      // Both pages should load without errors
      const [content1, content2] = await Promise.all([
        page1.content(),
        page2.content()
      ]);
      
      expect(content1).not.toContain('Error');
      expect(content2).not.toContain('Error');
      
    } finally {
      await context1.close();
      await context2.close();
    }
  });

  test('File upload edge cases', async ({ page }) => {
    await adminLogin(page);
    
    // Try to find file upload functionality
    const pages = [
      `${BASE_URL}/admin/customers/create`,
      `${BASE_URL}/admin/medical-records/create`,
      `${BASE_URL}/admin/users/create`
    ];
    
    for (const pageUrl of pages) {
      await page.goto(pageUrl);
      await page.waitForLoadState('networkidle');
      
      const fileInput = page.locator('input[type="file"]').first();
      if (await fileInput.isVisible({ timeout: 3000 })) {
        // Test with invalid file types (if file upload exists)
        // Note: This is just checking the interface exists and doesn't crash
        const isEnabled = await fileInput.isEnabled();
        expect(isEnabled).toBeTruthy();
        break;
      }
    }
  });

  test('API endpoint direct access attempts', async ({ page }) => {
    // Test direct access to API endpoints without proper authentication
    const apiEndpoints = [
      '/api/customers',
      '/api/reservations',
      '/api/medical-records',
      '/livewire/message/app.filament.resources.customer-resource.pages.list-customers'
    ];
    
    for (const endpoint of apiEndpoints) {
      const response = await page.goto(`${BASE_URL}${endpoint}`).catch(() => null);
      
      if (response) {
        const status = response.status();
        // Should either redirect to login (302) or return unauthorized (401/403)
        expect([302, 401, 403, 404]).toContain(status);
      }
    }
  });

  test('Form validation bypass attempts', async ({ page }) => {
    await adminLogin(page);
    
    await page.goto(`${BASE_URL}/admin/customers/create`);
    await page.waitForLoadState('networkidle');
    
    // Try to submit empty required fields
    const saveButton = page.locator('button[type="submit"], button:has-text("保存")').first();
    if (await saveButton.isVisible()) {
      await saveButton.click();
      await page.waitForLoadState('networkidle');
      
      // Should stay on the same page or show validation errors
      const url = page.url();
      const isStillOnCreatePage = url.includes('/create') || url.includes('/customers');
      expect(isStillOnCreatePage).toBeTruthy();
    }
    
    // Try invalid phone number formats
    const phoneFormats = ['abc-def-ghij', '123', '090-123-45678901234567890'];
    
    for (const invalidPhone of phoneFormats) {
      await page.fill('input[name="last_name"]', 'テスト');
      await page.fill('input[name="first_name"]', 'ユーザー');
      await page.fill('input[name="phone"]', invalidPhone);
      
      await saveButton.click();
      await page.waitForLoadState('networkidle');
      
      // Should not proceed with invalid data
      const pageContent = await page.content();
      const hasError = pageContent.includes('error') || pageContent.includes('エラー') || pageContent.includes('invalid');
      // If validation is working, we either see an error or stay on the page
      expect(page.url().includes('/create') || hasError).toBeTruthy();
    }
  });
});