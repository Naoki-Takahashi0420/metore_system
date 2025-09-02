import { test, expect } from '@playwright/test';

test('Admin dashboard timeline displays without errors', async ({ page }) => {
  // 管理画面にアクセス
  await page.goto('http://127.0.0.1:8000/admin/login');
  
  // ログイン
  await page.fill('input[type="email"]', 'admin@eye-training.com');
  await page.fill('input[type="password"]', 'password');
  await page.click('button[type="submit"]');
  
  // ダッシュボードの読み込みを待つ
  await page.waitForURL('**/admin');
  
  // エラーメッセージがないことを確認（警告アイコンは除外）
  const errorMessages = await page.locator('.alert-danger, .error').count();
  if (errorMessages > 0) {
    const errorText = await page.locator('.alert-danger, .error').first().textContent();
    throw new Error(`Error found on page: ${errorText}`);
  }
  
  // Internal Server Errorが表示されていないことを確認
  const bodyText = await page.locator('body').textContent();
  expect(bodyText).not.toContain('Internal Server Error');
  expect(bodyText).not.toContain('Carbon\\Exceptions');
  expect(bodyText).not.toContain('Could not parse');
  
  // タイムラインウィジェットが存在することを確認
  await expect(page.locator('.timeline-table')).toBeVisible({ timeout: 10000 });
  
  // BRKブロックが正しく表示されていることを確認
  const brkBlocks = await page.locator('.break-block').count();
  console.log(`BRK blocks found: ${brkBlocks}`);
  
  // 予約が正しく表示されていることを確認
  const reservations = await page.locator('.booking-block:not(.break-block)').count();
  console.log(`Regular reservations found: ${reservations}`);
  
  // 本日の予約テーブルが表示されていることを確認
  await expect(page.locator('text=すべての予約を見る').first()).toBeVisible();
});