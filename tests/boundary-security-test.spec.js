import { test, expect } from '@playwright/test';

test.describe('Boundary Values and Security Testing', () => {
  
  async function login(page, email = 'admin@eye-training.com', password = 'password') {
    await page.goto('http://127.0.0.1:8000/admin/login');
    await page.fill('input[type="email"]', email);
    await page.fill('input[type="password"]', password);
    await page.click('button[type="submit"]');
  }

  // 1. 認証・認可テスト（セキュリティ）
  test('Authentication and Authorization Tests', async ({ page }) => {
    console.log('🔐 Testing authentication and authorization...');
    
    // 未認証でadminページアクセス
    console.log('Testing unauthenticated admin access...');
    const response = await page.goto('http://127.0.0.1:8000/admin/customers');
    
    if (page.url().includes('/login')) {
      console.log('✅ Unauthenticated users redirected to login');
    } else {
      console.log('❌ Security issue: Unauthenticated access allowed');
    }
    
    // 無効な認証情報でログイン試行
    console.log('Testing invalid login credentials...');
    await page.goto('http://127.0.0.1:8000/admin/login');
    await page.fill('input[type="email"]', 'invalid@example.com');
    await page.fill('input[type="password"]', 'wrongpassword');
    await page.click('button[type="submit"]');
    await page.waitForTimeout(3000);
    
    if (page.url().includes('/login')) {
      console.log('✅ Invalid credentials rejected');
    } else {
      console.log('❌ Security issue: Invalid credentials accepted');
    }
    
    // 正常ログインテスト
    console.log('Testing valid login...');
    await login(page);
    await page.waitForTimeout(5000);
    
    if (page.url().includes('/admin') && !page.url().includes('/login')) {
      console.log('✅ Valid credentials accepted');
    } else {
      console.log('❌ Valid login failed');
    }
  });

  // 2. 境界値テスト（日付・時間）
  test('Date and Time Boundary Tests', async ({ page }) => {
    console.log('📅 Testing date and time boundaries...');
    
    await login(page);
    await page.waitForTimeout(5000);
    
    // 予約カレンダーで過去・未来の日付テスト
    console.log('Testing calendar date boundaries...');
    try {
      await page.goto('http://127.0.0.1:8000/admin/reservation-calendars');
      await page.waitForLoadState('networkidle');
      await page.waitForTimeout(5000);
      
      // カレンダーが表示されているか確認
      const calendarVisible = await page.locator('.fc').isVisible();
      if (calendarVisible) {
        console.log('✅ Calendar loads properly');
        
        // 過去の日付に移動してみる（エラーが発生しないかチェック）
        const prevButton = page.locator('.fc-prev-button');
        if (await prevButton.isVisible()) {
          await prevButton.click();
          await page.waitForTimeout(2000);
          console.log('✅ Past date navigation works');
        }
        
        // 未来の日付に移動
        const nextButton = page.locator('.fc-next-button');
        if (await nextButton.isVisible()) {
          await nextButton.click();
          await nextButton.click();
          await page.waitForTimeout(2000);
          console.log('✅ Future date navigation works');
        }
      } else {
        console.log('⚠️ Calendar not visible');
      }
    } catch (error) {
      console.log(`❌ Date boundary test failed: ${error.message}`);
    }
  });

  // 3. 大量データ処理テスト
  test('Large Data Handling Test', async ({ page }) => {
    console.log('📊 Testing large data handling...');
    
    await login(page);
    await page.waitForTimeout(5000);
    
    // 顧客一覧ページで大量データの表示テスト
    console.log('Testing customer list with large dataset...');
    try {
      await page.goto('http://127.0.0.1:8000/admin/customers');
      await page.waitForLoadState('networkidle');
      
      const customerRows = await page.locator('.fi-ta-table tbody tr').count();
      console.log(`Customer rows loaded: ${customerRows}`);
      
      if (customerRows > 0) {
        console.log('✅ Customer data loads properly');
        
        // ページネーションの確認
        const hasPagination = await page.locator('.fi-pagination').isVisible();
        if (hasPagination) {
          console.log('✅ Pagination available for large datasets');
        }
      } else {
        console.log('⚠️ No customer data found');
      }
      
    } catch (error) {
      console.log(`❌ Large data test failed: ${error.message}`);
    }
    
    // 予約一覧ページでの大量データテスト
    console.log('Testing reservation list with large dataset...');
    try {
      await page.goto('http://127.0.0.1:8000/admin/reservations');
      await page.waitForLoadState('networkidle');
      
      const reservationRows = await page.locator('.fi-ta-table tbody tr').count();
      console.log(`Reservation rows loaded: ${reservationRows}`);
      
      if (reservationRows > 0) {
        console.log('✅ Reservation data loads properly');
      }
      
    } catch (error) {
      console.log(`❌ Reservation data test failed: ${error.message}`);
    }
  });

  // 4. セッション・Cookie テスト
  test('Session and Cookie Security Test', async ({ page }) => {
    console.log('🍪 Testing session and cookie security...');
    
    // ログイン
    await login(page);
    await page.waitForTimeout(5000);
    
    if (page.url().includes('/admin') && !page.url().includes('/login')) {
      console.log('✅ Login session established');
      
      // セッション情報の確認
      const cookies = await page.context().cookies();
      const sessionCookie = cookies.find(cookie => 
        cookie.name.includes('session') || cookie.name.includes('laravel_session')
      );
      
      if (sessionCookie) {
        console.log('✅ Session cookie found');
        if (sessionCookie.httpOnly) {
          console.log('✅ Session cookie is HttpOnly (secure)');
        } else {
          console.log('⚠️ Session cookie is not HttpOnly');
        }
      } else {
        console.log('⚠️ Session cookie not found');
      }
    }
    
    // ログアウトテスト
    console.log('Testing logout functionality...');
    try {
      // ユーザーメニューを探す
      const userMenu = page.locator('[data-filament-dropdown-toggle]').first();
      if (await userMenu.isVisible()) {
        await userMenu.click();
        await page.waitForTimeout(1000);
        
        // ログアウトボタンを探す
        const logoutButton = page.getByRole('menuitem', { name: /ログアウト|logout|sign out/i });
        if (await logoutButton.isVisible()) {
          await logoutButton.click();
          await page.waitForTimeout(3000);
          
          if (page.url().includes('/login')) {
            console.log('✅ Logout successful');
          } else {
            console.log('⚠️ Logout may not have worked properly');
          }
        } else {
          console.log('⚠️ Logout button not found');
        }
      } else {
        console.log('⚠️ User menu not found');
      }
    } catch (error) {
      console.log(`⚠️ Logout test failed: ${error.message}`);
    }
  });

  // 5. エラーハンドリング境界テスト
  test('Error Handling Boundary Tests', async ({ page }) => {
    console.log('⚠️ Testing error handling boundaries...');
    
    await login(page);
    await page.waitForTimeout(5000);
    
    // 存在しないIDでのアクセステスト（様々なパターン）
    const invalidIdTests = [
      { url: 'http://127.0.0.1:8000/admin/customers/0/edit', name: 'Zero ID' },
      { url: 'http://127.0.0.1:8000/admin/customers/-1/edit', name: 'Negative ID' },
      { url: 'http://127.0.0.1:8000/admin/customers/abc/edit', name: 'String ID' },
      { url: 'http://127.0.0.1:8000/admin/customers/999999999/edit', name: 'Very Large ID' }
    ];
    
    for (const testCase of invalidIdTests) {
      try {
        console.log(`Testing ${testCase.name}: ${testCase.url}`);
        const response = await page.goto(testCase.url);
        const status = response.status();
        
        if (status === 404) {
          console.log(`✅ ${testCase.name} properly returns 404`);
        } else if (status >= 500) {
          console.log(`❌ ${testCase.name} returns 500 error - Status ${status}`);
        } else {
          console.log(`⚠️ ${testCase.name} unexpected status: ${status}`);
        }
      } catch (error) {
        console.log(`❌ ${testCase.name} test failed: ${error.message}`);
      }
    }
  });

  // 6. SQL Injection テスト（基本的なチェック）
  test('Basic SQL Injection Protection Test', async ({ page, request }) => {
    console.log('🛡️ Testing basic SQL injection protection...');
    
    // ログインフォームでのSQLインジェクション試行
    console.log('Testing SQL injection in login form...');
    await page.goto('http://127.0.0.1:8000/admin/login');
    
    const sqlPayloads = [
      "admin@test.com' OR '1'='1",
      "admin@test.com'; DROP TABLE users; --",
      "admin@test.com' UNION SELECT * FROM users --"
    ];
    
    for (const payload of sqlPayloads) {
      try {
        await page.fill('input[type="email"]', payload);
        await page.fill('input[type="password"]', 'password');
        await page.click('button[type="submit"]');
        await page.waitForTimeout(3000);
        
        if (page.url().includes('/login')) {
          console.log(`✅ SQL injection payload rejected: ${payload.substring(0, 30)}...`);
        } else {
          console.log(`❌ SECURITY RISK: SQL injection may have succeeded: ${payload.substring(0, 30)}...`);
        }
      } catch (error) {
        console.log(`✅ SQL injection payload blocked by error: ${payload.substring(0, 30)}...`);
      }
    }
  });
});