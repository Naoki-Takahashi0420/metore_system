import { test } from '@playwright/test';

test('管理画面のメニューカテゴリ表示を確認', async ({ page }) => {
  // ログイン
  await page.goto('http://127.0.0.1:8000/admin/login');
  await page.fill('input[type="email"]', 'naoki@yumeno-marketing.jp');
  await page.fill('input[type="password"]', 'Takahashi5000');
  await page.click('button[type="submit"]');
  
  // メニューカテゴリページへ
  await page.waitForTimeout(2000);
  await page.goto('http://127.0.0.1:8000/admin/menu-categories');
  
  // スクリーンショットを撮る
  await page.screenshot({ path: 'menu-categories.png', fullPage: true });
  
  // メニュー統合管理ページへ
  await page.goto('http://127.0.0.1:8000/admin/menu-manager');
  await page.waitForTimeout(2000);
  
  // スクリーンショットを撮る
  await page.screenshot({ path: 'menu-manager.png', fullPage: true });
});