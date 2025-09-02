import { test, expect } from '@playwright/test';

test.describe('Dashboard 404 Debug', () => {
  test('Check for 404 errors on dashboard', async ({ page }) => {
    // Basic認証
    await page.setExtraHTTPHeaders({
      'Authorization': 'Basic ' + Buffer.from('user:password').toString('base64')
    });

    // リクエストログを記録
    const requests = [];
    const responses = [];
    
    page.on('request', request => {
      requests.push({
        url: request.url(),
        method: request.method()
      });
    });
    
    page.on('response', response => {
      responses.push({
        url: response.url(),
        status: response.status(),
        statusText: response.statusText()
      });
      
      // 404エラーを検出
      if (response.status() === 404) {
        console.log(`❌ 404 Error: ${response.url()}`);
      }
    });
    
    // コンソールログを記録
    page.on('console', msg => {
      if (msg.type() === 'error') {
        console.log(`Console error: ${msg.text()}`);
      }
    });
    
    // ダッシュボードページにアクセス
    await page.goto('http://127.0.0.1:8000/customer/dashboard');
    
    // ページが完全にロードされるまで待機
    await page.waitForTimeout(3000);
    
    // 404エラーがあったリクエストをリスト
    const notFoundRequests = responses.filter(r => r.status === 404);
    
    console.log('\n=== 404 Errors Found ===');
    notFoundRequests.forEach(req => {
      console.log(`URL: ${req.url}`);
      console.log(`Status: ${req.status} ${req.statusText}`);
      console.log('---');
    });
    
    // 全リクエストのサマリー
    console.log('\n=== All Network Requests ===');
    const statusGroups = {};
    responses.forEach(r => {
      const status = r.status;
      if (!statusGroups[status]) {
        statusGroups[status] = [];
      }
      statusGroups[status].push(r.url);
    });
    
    Object.keys(statusGroups).sort().forEach(status => {
      console.log(`\nStatus ${status}: ${statusGroups[status].length} requests`);
      if (status === '404') {
        statusGroups[status].forEach(url => {
          console.log(`  - ${url}`);
        });
      }
    });
    
    // スクリーンショットを撮影
    await page.screenshot({ path: 'dashboard-debug.png', fullPage: true });
    
    // 404エラーがないことを確認
    expect(notFoundRequests.length).toBe(0);
  });
});