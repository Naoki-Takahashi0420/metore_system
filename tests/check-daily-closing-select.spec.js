import { test, expect } from '@playwright/test';

test('日次精算のセレクトボックス動作確認', async ({ page }) => {
  // ログイン
  await page.goto('http://localhost:8000/admin/login');
  await page.fill('input[type="email"]', 'naoki@yumeno-marketing.jp');
  await page.fill('input[type="password"]', 'Takahashi5000');
  await page.click('button[type="submit"]');
  await page.waitForURL('**/admin', { timeout: 10000 });

  // 日次精算に移動
  await page.goto('http://localhost:8000/admin/sales/daily-closing');
  await page.waitForTimeout(3000);

  console.log('\n📊 === 日次精算ページのセレクトボックス ===');

  // セレクトボックスを取得
  const storeSelect = await page.locator('select').first();

  // 現在選択されている値
  const currentValue = await storeSelect.inputValue();
  console.log(`  現在の選択値: "${currentValue}"`);

  // 全オプションを確認
  const options = await storeSelect.locator('option').all();
  console.log(`\n  オプション数: ${options.length}件`);

  for (let i = 0; i < options.length; i++) {
    const option = options[i];
    const value = await option.getAttribute('value');
    const text = await option.textContent();
    const selected = await option.evaluate(el => el.selected);
    console.log(`  ${i + 1}. value="${value}" text="${text}" ${selected ? '✅ 選択中' : ''}`);
  }

  // 2つめのオプションを選択してみる
  if (options.length >= 2) {
    const secondOptionValue = await options[1].getAttribute('value');
    console.log(`\n  2つめのオプションを選択: value="${secondOptionValue}"`);

    await storeSelect.selectOption(secondOptionValue);
    await page.waitForTimeout(2000);

    const newValue = await storeSelect.inputValue();
    console.log(`  選択後の値: "${newValue}"`);
    console.log(`  選択成功: ${newValue === secondOptionValue ? '✅' : '❌'}`);
  }

  console.log('\n');
});
