import { test, expect } from '@playwright/test';

test.describe('Comprehensive Error Testing - 500/404/PHP Errors', () => {
  
  // 認証が必要なページのテスト用ログイン
  async function login(page) {
    await page.goto('http://127.0.0.1:8000/admin/login');
    await page.fill('input[type="email"]', 'admin@eye-training.com');
    await page.fill('input[type="password"]', 'password');
    await page.click('button[type="submit"]');
    await page.waitForURL('**/admin', { timeout: 10000 });
  }

  // 1. 基本ページアクセステスト（404エラーチェック）
  test('Basic page access - Check 404 errors', async ({ page }) => {
    const pages = [
      { url: 'http://127.0.0.1:8000/', name: 'Home Page' },
      { url: 'http://127.0.0.1:8000/stores', name: 'Stores Page' },
      { url: 'http://127.0.0.1:8000/reservation/store', name: 'Reservation Store Selection' },
      { url: 'http://127.0.0.1:8000/health', name: 'Health Check' },
      { url: 'http://127.0.0.1:8000/admin/login', name: 'Admin Login' }
    ];

    for (const pageInfo of pages) {
      console.log(`Testing: ${pageInfo.name}`);
      
      const response = await page.goto(pageInfo.url);
      const status = response.status();
      
      if (status >= 400) {
        console.log(`❌ ERROR: ${pageInfo.name} - Status ${status}`);
        
        // Check for specific error content
        const content = await page.content();
        if (content.includes('500') || content.includes('Fatal error') || content.includes('Parse error')) {
          console.log(`🚨 CRITICAL: PHP Error detected on ${pageInfo.name}`);
        }
      } else {
        console.log(`✅ OK: ${pageInfo.name} - Status ${status}`);
      }
    }
  });

  // 2. 管理画面全ページテスト（500エラーチェック）
  test('Admin pages - Check 500 errors', async ({ page }) => {
    await login(page);
    
    const adminPages = [
      { url: '/admin', name: 'Dashboard' },
      { url: '/admin/stores', name: 'Store Management' },
      { url: '/admin/menus', name: 'Menu Management' },
      { url: '/admin/customers', name: 'Customer Management' },
      { url: '/admin/reservations', name: 'Reservation Management' },
      { url: '/admin/reservation-calendars', name: 'Reservation Calendar' },
      { url: '/admin/users', name: 'User Management' },
      { url: '/admin/medical-records', name: 'Medical Records' },
      { url: '/admin/sales', name: 'Sales Management' },
      { url: '/admin/shifts', name: 'Shift Management' }
    ];

    for (const pageInfo of adminPages) {
      try {
        console.log(`Testing admin page: ${pageInfo.name}`);
        
        const response = await page.goto(`http://127.0.0.1:8000${pageInfo.url}`);
        const status = response.status();
        
        if (status >= 500) {
          console.log(`❌ 500 ERROR: ${pageInfo.name} - Status ${status}`);
          
          // Check for PHP errors in content
          const content = await page.content();
          if (content.includes('Fatal error') || content.includes('Parse error') || content.includes('Call to undefined')) {
            console.log(`🚨 CRITICAL PHP ERROR: ${pageInfo.name}`);
            console.log('Error content preview:', content.substring(0, 500));
          }
        } else if (status >= 400) {
          console.log(`⚠️  ${status} ERROR: ${pageInfo.name}`);
        } else {
          console.log(`✅ OK: ${pageInfo.name} - Status ${status}`);
        }
        
        // Wait for page to load completely
        await page.waitForLoadState('networkidle', { timeout: 10000 });
        
      } catch (error) {
        console.log(`❌ EXCEPTION: ${pageInfo.name} - ${error.message}`);
      }
    }
  });

  // 3. フォーム送信テスト（バリデーションエラーチェック）
  test('Form submission - Validation and server errors', async ({ page }) => {
    await login(page);
    
    // 顧客作成フォームテスト
    console.log('Testing customer creation form...');
    try {
      await page.goto('http://127.0.0.1:8000/admin/customers/create');
      
      // 空フォーム送信
      await page.click('button[type="submit"]');
      await page.waitForTimeout(2000);
      
      // バリデーションエラーの確認
      const hasValidationErrors = await page.locator('.fi-fo-field-wrp-error-message, .error, .invalid-feedback').count() > 0;
      if (hasValidationErrors) {
        console.log('✅ Validation errors properly displayed');
      } else {
        console.log('⚠️  No validation errors found - may need investigation');
      }
      
    } catch (error) {
      console.log(`❌ Customer form test failed: ${error.message}`);
    }

    // メニュー作成フォームテスト  
    console.log('Testing menu creation form...');
    try {
      await page.goto('http://127.0.0.1:8000/admin/menus/create');
      
      // 不正データで送信
      await page.fill('input[name="name"]', ''); // 空の名前
      await page.fill('input[name="price"]', '-100'); // 負の価格
      await page.click('button[type="submit"]');
      await page.waitForTimeout(2000);
      
      const pageContent = await page.content();
      if (pageContent.includes('500') || pageContent.includes('Fatal error')) {
        console.log('❌ 500 ERROR: Menu form submission failed');
      } else {
        console.log('✅ Menu form handled properly');
      }
      
    } catch (error) {
      console.log(`❌ Menu form test failed: ${error.message}`);
    }
  });

  // 4. API エンドポイントテスト
  test('API endpoints - Check errors', async ({ page, request }) => {
    const apiEndpoints = [
      { url: 'http://127.0.0.1:8000/api/line/webhook', method: 'POST', name: 'LINE Webhook' },
      { url: 'http://127.0.0.1:8000/receipt/json/1', name: 'Receipt JSON API' }
    ];

    for (const endpoint of apiEndpoints) {
      try {
        console.log(`Testing API: ${endpoint.name}`);
        
        let response;
        if (endpoint.method === 'POST') {
          response = await request.post(endpoint.url, {
            data: { test: 'data' },
            headers: { 'Content-Type': 'application/json' }
          });
        } else {
          // Login first for authenticated endpoints
          await login(page);
          await page.goto(endpoint.url);
          continue; // Skip REST API call for browser-based tests
        }
        
        const status = response.status();
        if (status >= 500) {
          console.log(`❌ 500 ERROR: ${endpoint.name} - Status ${status}`);
          const body = await response.text();
          if (body.includes('Fatal error') || body.includes('Parse error')) {
            console.log(`🚨 CRITICAL PHP ERROR in API: ${endpoint.name}`);
          }
        } else {
          console.log(`✅ API OK: ${endpoint.name} - Status ${status}`);
        }
        
      } catch (error) {
        console.log(`❌ API TEST FAILED: ${endpoint.name} - ${error.message}`);
      }
    }
  });

  // 5. 存在しないリソースアクセステスト（404チェック）
  test('Non-existent resources - Check 404 handling', async ({ page }) => {
    await login(page);
    
    const nonExistentPages = [
      'http://127.0.0.1:8000/admin/nonexistent',
      'http://127.0.0.1:8000/admin/customers/99999/edit',
      'http://127.0.0.1:8000/admin/reservations/99999/edit',
      'http://127.0.0.1:8000/receipt/print/99999',
      'http://127.0.0.1:8000/invalid-page'
    ];

    for (const url of nonExistentPages) {
      try {
        console.log(`Testing 404 for: ${url}`);
        
        const response = await page.goto(url);
        const status = response.status();
        
        if (status === 404) {
          console.log(`✅ Proper 404: ${url}`);
        } else if (status >= 500) {
          console.log(`❌ 500 ERROR instead of 404: ${url} - Status ${status}`);
          
          const content = await page.content();
          if (content.includes('Fatal error') || content.includes('Parse error')) {
            console.log(`🚨 CRITICAL: PHP Error on 404 page: ${url}`);
          }
        } else {
          console.log(`⚠️  Unexpected status: ${url} - Status ${status}`);
        }
        
      } catch (error) {
        console.log(`❌ 404 test failed: ${url} - ${error.message}`);
      }
    }
  });

  // 6. ブラウザコンソールエラーチェック
  test('Browser console errors', async ({ page }) => {
    const consoleErrors = [];
    const jsErrors = [];
    
    page.on('console', msg => {
      if (msg.type() === 'error') {
        consoleErrors.push(msg.text());
      }
    });
    
    page.on('pageerror', error => {
      jsErrors.push(error.message);
    });
    
    await login(page);
    
    // 主要ページでJSエラーチェック
    const pagestoCheck = [
      'http://127.0.0.1:8000/admin',
      'http://127.0.0.1:8000/admin/reservation-calendars',
      'http://127.0.0.1:8000/admin/sales'
    ];
    
    for (const url of pagestoCheck) {
      console.log(`Checking JS errors for: ${url}`);
      await page.goto(url);
      await page.waitForLoadState('networkidle');
      await page.waitForTimeout(3000); // Wait for all JS to execute
    }
    
    if (consoleErrors.length > 0) {
      console.log('❌ Console Errors Found:');
      consoleErrors.forEach(error => console.log(`  - ${error}`));
    } else {
      console.log('✅ No console errors found');
    }
    
    if (jsErrors.length > 0) {
      console.log('❌ JavaScript Errors Found:');
      jsErrors.forEach(error => console.log(`  - ${error}`));
    } else {
      console.log('✅ No JavaScript errors found');
    }
  });
});