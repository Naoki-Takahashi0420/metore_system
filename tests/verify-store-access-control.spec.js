import { test, expect } from '@playwright/test';

test('super_adminで売上管理を開いた時に「全店舗」が選択されているか確認', async ({ page }) => {
  // ログイン
  await page.goto('http://localhost:8000/admin/login');
  await page.fill('input[type="email"]', 'naoki@yumeno-marketing.jp');
  await page.fill('input[type="password"]', 'Takahashi5000');
  await page.click('button[type="submit"]');
  await page.waitForURL('**/admin', { timeout: 10000 });

  // 売上管理に移動
  await page.goto('http://localhost:8000/admin/sales');
  await page.waitForTimeout(3000);

  console.log('\n📊 === 売上管理画面（super_admin） ===');

  // セレクトボックスの値を確認
  const storeSelect = await page.locator('select').first();
  const selectedValue = await storeSelect.inputValue();

  console.log(`  選択されている値: "${selectedValue}" ${selectedValue === '' ? '✅ (全店舗)' : '❌'}`);

  // 「全店舗」オプションが存在するか確認
  const allStoresOption = await storeSelect.locator('option[value=""]').count();
  console.log(`  「全店舗」オプション: ${allStoresOption > 0 ? '✅ 存在する' : '❌ 存在しない'}`);

  // サブスクリプション情報を確認
  const pageText = await page.textContent('body');
  const contractMatch = pageText.match(/契約人数[:\s]*(\d+)人/);
  const revenueMatch = pageText.match(/今月入金見込み[:\s]*¥([\d,]+)/);

  console.log(`  契約人数: ${contractMatch ? contractMatch[1] : '不明'}人`);
  console.log(`  今月入金見込み: ¥${revenueMatch ? revenueMatch[1] : '不明'}`);
  console.log(`  期待値: 89人、¥1,469,000 (全店舗合計)`);

  // 日次精算画面も確認
  console.log('\n📊 === 日次精算画面（super_admin） ===');

  await page.goto('http://localhost:8000/admin/sales/daily-closing');
  await page.waitForTimeout(3000);

  const dailyStoreSelect = await page.locator('select').first();
  const dailySelectedValue = await dailyStoreSelect.inputValue();

  console.log(`  選択されている値: "${dailySelectedValue}" ${dailySelectedValue === '' ? '✅ (全店舗)' : '❌'}`);

  // 「全店舗」オプションが存在するか確認
  const dailyAllStoresOption = await dailyStoreSelect.locator('option[value=""]').count();
  console.log(`  「全店舗」オプション: ${dailyAllStoresOption > 0 ? '✅ 存在する' : '❌ 存在しない'}`);

  console.log('\n');
});

test('managerで売上管理を開いた時に自店舗のみ表示されるか確認', async ({ page }) => {
  // ログイン（銀座本店のmanager）
  await page.goto('http://localhost:8000/admin/login');
  await page.fill('input[type="email"]', 'metoreginza@gmail.com');
  await page.fill('input[type="password"]', 'password');
  await page.click('button[type="submit"]');
  await page.waitForURL('**/admin', { timeout: 10000 });

  // 売上管理に移動
  await page.goto('http://localhost:8000/admin/sales');
  await page.waitForTimeout(3000);

  console.log('\n📊 === 売上管理画面（manager - 銀座本店） ===');

  // セレクトボックスの全オプションを確認
  const storeSelect = await page.locator('select').first();
  const options = await storeSelect.locator('option').all();

  console.log(`  表示されている店舗数: ${options.length}件`);

  for (const option of options) {
    const value = await option.getAttribute('value');
    const text = await option.textContent();
    console.log(`    - ${text} (ID: ${value})`);
  }

  console.log(`  期待値: 1件のみ（銀座本店）、「全店舗」オプションなし`);

  // 「全店舗」オプションが存在しないことを確認
  const allStoresOption = await storeSelect.locator('option[value=""]').count();
  console.log(`  「全店舗」オプション: ${allStoresOption === 0 ? '✅ 存在しない（正しい）' : '❌ 存在する（エラー）'}`);

  console.log('\n');
});
