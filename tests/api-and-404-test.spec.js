import { test, expect } from '@playwright/test';

test.describe('API and 404 Error Testing', () => {
  
  async function login(page) {
    await page.goto('http://127.0.0.1:8000/admin/login');
    await page.fill('input[type="email"]', 'admin@eye-training.com');
    await page.fill('input[type="password"]', 'password');
    await page.click('button[type="submit"]');
    await page.waitForURL('**/admin', { timeout: 10000 });
  }

  // API エンドポイントテスト
  test('API endpoints - Check errors', async ({ page, request }) => {
    const apiEndpoints = [
      { url: 'http://127.0.0.1:8000/api/line/webhook', method: 'POST', name: 'LINE Webhook' },
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

  // 存在しないリソースアクセステスト（404チェック）
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

  // Receipt JSON APIテスト
  test('Receipt JSON API test', async ({ page }) => {
    await login(page);
    
    // 実際の売上IDを取得
    try {
      await page.goto('http://127.0.0.1:8000/admin/sales');
      await page.waitForLoadState('networkidle');
      
      // 売上データが存在するかチェック
      const hasSales = await page.locator('.fi-ta-table tbody tr').count() > 0;
      
      if (hasSales) {
        // 最初の売上の詳細ページに移動してIDを取得
        await page.locator('.fi-ta-table tbody tr').first().click();
        await page.waitForTimeout(2000);
        
        const currentUrl = page.url();
        const salesIdMatch = currentUrl.match(/\/sales\/(\d+)/);
        
        if (salesIdMatch) {
          const salesId = salesIdMatch[1];
          console.log(`Found sales ID: ${salesId}`);
          
          // Receipt JSON APIテスト
          const jsonUrl = `http://127.0.0.1:8000/receipt/json/${salesId}`;
          console.log(`Testing Receipt JSON API: ${jsonUrl}`);
          
          const response = await page.goto(jsonUrl);
          const status = response.status();
          
          if (status === 200) {
            const content = await page.content();
            if (content.includes('"customer"') && content.includes('"store"')) {
              console.log(`✅ Receipt JSON API works: ${jsonUrl}`);
            } else {
              console.log(`⚠️  Receipt JSON response may be invalid: ${jsonUrl}`);
            }
          } else if (status >= 500) {
            console.log(`❌ 500 ERROR: Receipt JSON API - ${jsonUrl} - Status ${status}`);
          } else {
            console.log(`⚠️  Unexpected status: Receipt JSON API - ${jsonUrl} - Status ${status}`);
          }
        }
      } else {
        console.log('⚠️  No sales data found for Receipt JSON API test');
      }
      
    } catch (error) {
      console.log(`❌ Receipt JSON API test failed: ${error.message}`);
    }
  });

  // ブラウザコンソールエラーチェック
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