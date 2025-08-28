import { test, expect } from '@playwright/test';

test('ガントチャート表示確認', async ({ page }) => {
  // 管理画面にログイン
  await page.goto('http://127.0.0.1:8000/admin/login');
  
  // ページが完全に読み込まれるのを待つ
  await page.waitForLoadState('domcontentloaded');
  await page.waitForTimeout(1000);
  
  // 入力フィールドを明示的に待機してからフィル
  await page.waitForSelector('input[id="data.email"]', { visible: true });
  await page.click('input[id="data.email"]');
  await page.fill('input[id="data.email"]', 'naoki@yumeno-marketing.jp');
  
  await page.waitForSelector('input[id="data.password"]', { visible: true });
  await page.click('input[id="data.password"]');
  await page.fill('input[id="data.password"]', 'Takahashi5000');
  
  await page.click('button[type="submit"]');
  
  // ログイン成功を確認
  await expect(page).toHaveURL(/.*admin/);
  
  // 予約カレンダーに移動
  try {
    await page.goto('http://127.0.0.1:8000/admin/reservation-calendars', { waitUntil: 'networkidle' });
  } catch (error) {
    console.log('Navigation error:', error.message);
    // エラー時はダッシュボードから移動を試みる
    await page.click('text=予約管理');
    await page.waitForTimeout(1000);
    await page.click('text=予約カレンダー');
  }
  
  // ページが読み込まれるのを待つ
  await page.waitForTimeout(3000);
  
  // ガントチャート関連要素の存在確認
  await expect(page.locator('text=予約表')).toBeVisible();
  
  // テーブルの存在確認
  const table = page.locator('table');
  await expect(table).toBeVisible();
  
  // 時間軸ヘッダーの確認
  const timeHeaders = page.locator('th:has-text("09:")');
  console.log('Time headers count:', await timeHeaders.count());
  
  // 店舗名の確認
  const storeHeaders = page.locator('text=店舗');
  console.log('Store headers count:', await storeHeaders.count());
  
  // スクリーンショットを撮影
  await page.screenshot({ 
    path: 'gantt-chart-debug.png', 
    fullPage: true 
  });
  
  console.log('ガントチャートのスクリーンショットを撮影しました: gantt-chart-debug.png');
});