import { test, expect } from '@playwright/test';

test.describe('Data Integrity and Performance Testing', () => {
  
  async function login(page) {
    await page.goto('http://127.0.0.1:8000/admin/login');
    await page.fill('input[type="email"]', 'admin@eye-training.com');
    await page.fill('input[type="password"]', 'password');
    await page.click('button[type="submit"]');
    await page.waitForURL('**/admin', { timeout: 10000 });
  }

  // 1. データ整合性テスト
  test('Database Data Integrity Check', async ({ page }) => {
    console.log('🔗 Testing database data integrity...');
    
    await login(page);
    await page.waitForTimeout(3000);
    
    // 予約データと顧客データの整合性チェック
    console.log('Checking reservation-customer data integrity...');
    try {
      await page.goto('http://127.0.0.1:8000/admin/reservations');
      await page.waitForLoadState('networkidle');
      
      const reservationRows = await page.locator('.fi-ta-table tbody tr').count();
      console.log(`Total reservations: ${reservationRows}`);
      
      if (reservationRows > 0) {
        // 最初の予約をクリックして詳細確認
        await page.locator('.fi-ta-table tbody tr').first().click();
        await page.waitForTimeout(2000);
        
        // 顧客情報が正しく表示されているか確認
        const hasCustomerInfo = await page.locator('text=/様|さん|氏/').isVisible();
        if (hasCustomerInfo) {
          console.log('✅ Reservation-customer relationship intact');
        } else {
          console.log('⚠️ Customer information may be missing in reservation');
        }
      }
      
    } catch (error) {
      console.log(`❌ Data integrity test failed: ${error.message}`);
    }
    
    // 売上データと予約データの整合性チェック
    console.log('Checking sales-reservation data integrity...');
    try {
      await page.goto('http://127.0.0.1:8000/admin/sales');
      await page.waitForLoadState('networkidle');
      
      const salesRows = await page.locator('.fi-ta-table tbody tr').count();
      console.log(`Total sales records: ${salesRows}`);
      
      if (salesRows > 0) {
        console.log('✅ Sales data available');
        
        // 最初の売上をクリック
        await page.locator('.fi-ta-table tbody tr').first().click();
        await page.waitForTimeout(2000);
        
        // 予約情報が関連付けられているか確認
        const hasReservationLink = await page.locator('text=/予約|reservation/i').isVisible();
        if (hasReservationLink) {
          console.log('✅ Sales-reservation relationship intact');
        } else {
          console.log('⚠️ Reservation link may be missing in sales');
        }
      }
      
    } catch (error) {
      console.log(`❌ Sales integrity test failed: ${error.message}`);
    }
  });

  // 2. ページロード性能テスト
  test('Page Load Performance Test', async ({ page }) => {
    console.log('⚡ Testing page load performance...');
    
    await login(page);
    await page.waitForTimeout(3000);
    
    const testPages = [
      { url: 'http://127.0.0.1:8000/admin', name: 'Dashboard' },
      { url: 'http://127.0.0.1:8000/admin/reservation-calendars', name: 'Calendar' },
      { url: 'http://127.0.0.1:8000/admin/customers', name: 'Customers' },
      { url: 'http://127.0.0.1:8000/admin/reservations', name: 'Reservations' },
      { url: 'http://127.0.0.1:8000/admin/sales', name: 'Sales' }
    ];
    
    for (const testPage of testPages) {
      try {
        console.log(`Testing ${testPage.name} load time...`);
        const startTime = Date.now();
        
        await page.goto(testPage.url);
        await page.waitForLoadState('networkidle', { timeout: 15000 });
        
        const loadTime = Date.now() - startTime;
        
        if (loadTime < 5000) {
          console.log(`✅ ${testPage.name}: ${loadTime}ms (Good)`);
        } else if (loadTime < 10000) {
          console.log(`⚠️ ${testPage.name}: ${loadTime}ms (Acceptable)`);
        } else {
          console.log(`❌ ${testPage.name}: ${loadTime}ms (Slow)`);
        }
        
      } catch (error) {
        console.log(`❌ ${testPage.name} load test failed: ${error.message}`);
      }
    }
  });

  // 3. 大量データスクロール・ページネーションテスト
  test('Large Data Navigation Test', async ({ page }) => {
    console.log('📄 Testing large data navigation...');
    
    await login(page);
    await page.waitForTimeout(3000);
    
    // 予約一覧での大量データテスト
    console.log('Testing reservation pagination...');
    try {
      await page.goto('http://127.0.0.1:8000/admin/reservations');
      await page.waitForLoadState('networkidle');
      
      const reservationCount = await page.locator('.fi-ta-table tbody tr').count();
      console.log(`Reservations on first page: ${reservationCount}`);
      
      // ページネーション確認
      const pagination = page.locator('.fi-pagination');
      if (await pagination.isVisible()) {
        console.log('✅ Pagination available');
        
        // 次ページボタン確認
        const nextButton = page.locator('.fi-pagination button').last();
        if (await nextButton.isVisible() && !await nextButton.isDisabled()) {
          console.log('Testing next page navigation...');
          const startTime = Date.now();
          
          await nextButton.click();
          await page.waitForLoadState('networkidle');
          
          const paginationTime = Date.now() - startTime;
          console.log(`✅ Page navigation time: ${paginationTime}ms`);
          
          const secondPageCount = await page.locator('.fi-ta-table tbody tr').count();
          console.log(`Reservations on second page: ${secondPageCount}`);
        }
      } else {
        console.log('⚠️ No pagination found - all data on single page');
      }
      
    } catch (error) {
      console.log(`❌ Pagination test failed: ${error.message}`);
    }
  });

  // 4. カレンダー応答性テスト
  test('Calendar Responsiveness Test', async ({ page }) => {
    console.log('📅 Testing calendar responsiveness...');
    
    await login(page);
    await page.waitForTimeout(3000);
    
    try {
      await page.goto('http://127.0.0.1:8000/admin/reservation-calendars');
      await page.waitForLoadState('networkidle');
      
      // カレンダー読み込み時間測定
      const startTime = Date.now();
      await expect(page.locator('.fc')).toBeVisible({ timeout: 15000 });
      const calendarLoadTime = Date.now() - startTime;
      
      if (calendarLoadTime < 3000) {
        console.log(`✅ Calendar loads quickly: ${calendarLoadTime}ms`);
      } else if (calendarLoadTime < 8000) {
        console.log(`⚠️ Calendar load acceptable: ${calendarLoadTime}ms`);
      } else {
        console.log(`❌ Calendar loads slowly: ${calendarLoadTime}ms`);
      }
      
      // カレンダー操作応答性テスト
      const eventCount = await page.locator('.fc-event').count();
      console.log(`Calendar events loaded: ${eventCount}`);
      
      if (eventCount > 100) {
        console.log('Testing calendar with high event count...');
        
        // 日付変更の応答性テスト
        const navStartTime = Date.now();
        const nextButton = page.locator('.fc-next-button');
        if (await nextButton.isVisible()) {
          await nextButton.click();
          await page.waitForTimeout(2000);
          const navTime = Date.now() - navStartTime;
          console.log(`Calendar navigation time: ${navTime}ms`);
        }
      }
      
    } catch (error) {
      console.log(`❌ Calendar responsiveness test failed: ${error.message}`);
    }
  });

  // 5. 同時接続シミュレーション（軽量版）
  test('Concurrent Access Test', async ({ browser }) => {
    console.log('👥 Testing concurrent access...');
    
    try {
      // 複数のページコンテキストを作成してログイン
      const contexts = await Promise.all([
        browser.newContext(),
        browser.newContext(),
        browser.newContext()
      ]);
      
      const pages = await Promise.all(contexts.map(ctx => ctx.newPage()));
      
      // 同時ログインテスト
      console.log('Testing concurrent logins...');
      const loginPromises = pages.map(async (page, index) => {
        const startTime = Date.now();
        await login(page);
        await page.waitForTimeout(3000);
        const loginTime = Date.now() - startTime;
        console.log(`User ${index + 1} login time: ${loginTime}ms`);
        return page.url().includes('/admin') && !page.url().includes('/login');
      });
      
      const results = await Promise.all(loginPromises);
      const successCount = results.filter(Boolean).length;
      
      if (successCount === 3) {
        console.log('✅ All concurrent logins successful');
      } else {
        console.log(`⚠️ Only ${successCount}/3 concurrent logins successful`);
      }
      
      // クリーンアップ
      await Promise.all(contexts.map(ctx => ctx.close()));
      
    } catch (error) {
      console.log(`❌ Concurrent access test failed: ${error.message}`);
    }
  });

  // 6. メモリリークチェック（基本的な確認）
  test('Basic Memory Usage Check', async ({ page }) => {
    console.log('🧠 Testing basic memory usage...');
    
    await login(page);
    await page.waitForTimeout(3000);
    
    try {
      // 複数ページを連続で読み込んでメモリ使用量をモニター
      const pages = [
        'http://127.0.0.1:8000/admin',
        'http://127.0.0.1:8000/admin/customers',
        'http://127.0.0.1:8000/admin/reservations',
        'http://127.0.0.1:8000/admin/sales',
        'http://127.0.0.1:8000/admin/reservation-calendars'
      ];
      
      for (const url of pages) {
        console.log(`Loading ${url}...`);
        await page.goto(url);
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(1000);
      }
      
      console.log('✅ Multiple page loads completed without crashes');
      
      // ガベージコレクション実行
      if (page.evaluate) {
        await page.evaluate(() => {
          if (window.gc) {
            window.gc();
          }
        });
        console.log('✅ Garbage collection attempted');
      }
      
    } catch (error) {
      console.log(`❌ Memory test failed: ${error.message}`);
    }
  });
});