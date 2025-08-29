import { test, expect } from '@playwright/test';

test('管理画面にアクセスして基本機能を確認', async ({ page }) => {
  // トップページにアクセス
  await page.goto('http://127.0.0.1:8000');
  console.log('トップページにアクセス成功');
  
  // 管理画面にアクセス
  await page.goto('http://127.0.0.1:8000/admin/login');
  console.log('ログインページにアクセス成功');
  
  // ログイン
  await page.fill('input[type="email"]', 'admin@eye-training.com');
  await page.fill('input[type="password"]', 'password');
  await page.click('button[type="submit"]');
  
  // ダッシュボードまで待つ
  await page.waitForURL('**/admin', { timeout: 10000 });
  console.log('ログイン成功');
  
  // スクリーンショットを撮る
  await page.screenshot({ path: 'test-results/admin-dashboard.png' });
  
  // メニューを確認
  const lineMenu = page.locator('text=LINE管理');
  if (await lineMenu.count() > 0) {
    console.log('LINE管理メニューが見つかりました');
    await lineMenu.first().click();
  }
  
  // 各ページをチェック
  const pages = [
    '/admin/store-line-settings',
    '/admin/line-reminder-rules',
    '/admin/line-message-templates'
  ];
  
  for (const url of pages) {
    await page.goto('http://127.0.0.1:8000' + url);
    await page.waitForLoadState('networkidle');
    const title = await page.title();
    console.log(`${url}: ${title}`);
    
    // 404エラーでないことを確認
    const body = await page.textContent('body');
    expect(body).not.toContain('404');
    expect(body).not.toContain('Not Found');
  }
  
  console.log('すべてのページに正常にアクセスできました');
});