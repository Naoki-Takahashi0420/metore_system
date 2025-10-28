import { test, expect } from '@playwright/test';

test('ローカル環境のダッシュボードと売上管理の数字を比較', async ({ page }) => {
  // ローカル環境にログイン
  await page.goto('http://localhost:8000/admin/login');
  await page.fill('input[type="email"]', 'naoki@yumeno-marketing.jp');
  await page.fill('input[type="password"]', 'Takahashi5000');
  await page.click('button[type="submit"]');
  await page.waitForURL('**/admin', { timeout: 10000 });
  await page.waitForTimeout(3000);

  console.log('\n📊 === ダッシュボードのウィジェット ===');

  // 店舗セレクトを銀座本店に設定
  const storeSelect = await page.locator('select').first();
  await storeSelect.selectOption('1'); // 銀座本店
  await page.waitForTimeout(2000);

  // ウィジェットの数字を取得
  const widgets = await page.locator('.fi-wi-stats-overview-stat').all();

  for (let i = 0; i < widgets.length; i++) {
    const widget = widgets[i];
    try {
      const label = await widget.locator('.fi-wi-stats-overview-stat-label').textContent();
      const value = await widget.locator('.fi-wi-stats-overview-stat-value').textContent();

      if (label && label.includes('契約') || label && label.includes('収益')) {
        console.log(`  ${label}: ${value}`);
      }
    } catch (e) {
      // Skip if not found
    }
  }

  console.log('\n📊 === 売上管理画面 ===');

  // 売上管理画面に移動
  await page.goto('http://localhost:8000/admin/sales');
  await page.waitForTimeout(3000);

  // 店舗セレクトを銀座本店に設定
  const salesStoreSelect = await page.locator('select').first();
  await salesStoreSelect.selectOption('1'); // 銀座本店
  await page.waitForTimeout(2000);

  // サブスクリプション情報を探す
  const pageText = await page.textContent('body');

  // 契約人数を探す
  const contractMatch = pageText.match(/契約人数[:\s]*(\d+)人/);
  if (contractMatch) {
    console.log(`  契約人数: ${contractMatch[1]}人`);
  }

  // 今月入金見込みを探す
  const revenueMatch = pageText.match(/今月入金見込み[:\s]*¥([\d,]+)/);
  if (revenueMatch) {
    console.log(`  今月入金見込み: ¥${revenueMatch[1]}`);
  }

  console.log('\n');
});
