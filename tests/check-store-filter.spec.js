import { test, expect } from '@playwright/test';

test('売上管理画面の店舗フィルタが効いているか確認', async ({ page }) => {
  // ログイン
  await page.goto('http://localhost:8000/admin/login');
  await page.fill('input[type="email"]', 'naoki@yumeno-marketing.jp');
  await page.fill('input[type="password"]', 'Takahashi5000');
  await page.click('button[type="submit"]');
  await page.waitForURL('**/admin', { timeout: 10000 });

  // 売上管理に移動
  await page.goto('http://localhost:8000/admin/sales');
  await page.waitForTimeout(3000);

  const storeSelect = await page.locator('select').first();

  // 銀座本店を選択
  console.log('\n📊 === 銀座本店（店舗ID: 1）===');
  await storeSelect.selectOption('1');
  await page.waitForTimeout(2000);

  let pageText = await page.textContent('body');
  let contractMatch = pageText.match(/契約人数[:\s]*(\d+)人/);
  let revenueMatch = pageText.match(/今月入金見込み[:\s]*¥([\d,]+)/);

  console.log(`  契約人数: ${contractMatch ? contractMatch[1] : '不明'}人`);
  console.log(`  今月入金見込み: ¥${revenueMatch ? revenueMatch[1] : '不明'}`);

  // 吉祥寺店を選択
  console.log('\n📊 === 吉祥寺店（店舗ID: 6）===');
  await storeSelect.selectOption('6');
  await page.waitForTimeout(2000);

  pageText = await page.textContent('body');
  contractMatch = pageText.match(/契約人数[:\s]*(\d+)人/);
  revenueMatch = pageText.match(/今月入金見込み[:\s]*¥([\d,]+)/);

  console.log(`  契約人数: ${contractMatch ? contractMatch[1] : '不明'}人`);
  console.log(`  今月入金見込み: ¥${revenueMatch ? revenueMatch[1] : '不明'}`);

  // すべての店舗オプションを確認
  console.log('\n📋 利用可能な店舗:');
  const options = await storeSelect.locator('option').all();
  for (const option of options) {
    const value = await option.getAttribute('value');
    const text = await option.textContent();
    console.log(`  ID ${value}: ${text}`);
  }

  console.log('\n');
});
