import { test, expect } from '@playwright/test';

test('オーナーが店舗フィルタで顧客を検索できる', async ({ page }) => {
  // オーナー髙橋でログイン
  await page.goto('http://localhost:8000/admin/login');
  await page.waitForSelector('input[type="email"]', { timeout: 10000 });
  await page.fill('input[type="email"]', 'dasuna2305@gmail.com');
  await page.fill('input[type="password"]', 'password');
  await page.click('button[type="submit"]');

  // ダッシュボードが表示されるまで待つ
  await page.waitForTimeout(3000);

  // 顧客管理ページに移動
  await page.goto('http://localhost:8000/admin/customers');
  await page.waitForTimeout(2000);

  console.log('顧客管理ページにアクセスしました');

  // ヘッダーの店舗セレクターで浜松町店（ID=7）を選択
  const storeSelector = await page.locator('select').first();
  await storeSelector.selectOption('7');
  await page.waitForTimeout(3000);

  console.log('浜松町店を選択しました');

  // 「顧客が見つかりません」が表示されているか確認
  const emptyMessage = await page.locator('text=顧客が見つかりません').count();

  // テーブルの行数を確認
  const rows = await page.locator('tbody tr').count();

  console.log(`空状態メッセージ: ${emptyMessage > 0 ? '表示されている' : '表示されていない'}`);
  console.log(`顧客数: ${rows}`);

  // スクリーンショットを撮る
  await page.screenshot({ path: 'owner-customer-filter-result.png', fullPage: true });

  if (emptyMessage > 0) {
    console.error('❌ 「顧客が見つかりません」が表示されています！');
  } else if (rows > 0) {
    console.log(`✅ ${rows}人の顧客が表示されています`);
  }

  expect(emptyMessage).toBe(0);
  expect(rows).toBeGreaterThan(0);
});
