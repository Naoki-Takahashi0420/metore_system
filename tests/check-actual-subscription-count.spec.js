import { test, expect } from '@playwright/test';

test('売上管理画面の実際のサブスク数を確認', async ({ page }) => {
  // ログイン
  await page.goto('https://reservation.meno-training.com/admin/login');
  await page.fill('input[type="email"]', 'naoki@yumeno-marketing.jp');
  await page.fill('input[type="password"]', 'Takahashi5000');
  await page.click('button[type="submit"]');
  await page.waitForURL('**/admin', { timeout: 10000 });

  // 売上管理に移動
  await page.goto('https://reservation.meno-training.com/admin/sales');
  await page.waitForTimeout(5000);

  console.log('\n📊 売上管理画面のサブスクリプション情報を確認');

  // 店舗セレクトを銀座本店に設定
  const storeSelect = await page.locator('select').first();
  await storeSelect.selectOption('1'); // 銀座本店
  await page.waitForTimeout(3000);

  console.log('\n🏪 店舗: 銀座本店');

  // ページの全テキストを取得
  const pageText = await page.textContent('body');

  // サブスクリプション関連の数字を探す
  const lines = pageText.split('\n').map(line => line.trim()).filter(line => line);

  console.log('\n🔍 サブスク関連の行を探索:');

  for (let i = 0; i < lines.length; i++) {
    const line = lines[i];
    if (line.includes('サブスク') || line.includes('契約') || line.includes('人数')) {
      console.log(`  ${line}`);
      // 次の数行も表示
      for (let j = 1; j <= 3 && i + j < lines.length; j++) {
        console.log(`    → ${lines[i + j]}`);
      }
    }
  }

  // 利用形態別件数のセクションを探す
  const sourceStats = await page.locator('text=利用形態別件数').locator('..').locator('..');

  if (sourceStats) {
    console.log('\n📈 利用形態別件数セクション:');
    const sourceText = await sourceStats.textContent();
    console.log(sourceText);
  }

  // スクリーンショットを保存
  await page.screenshot({ path: 'sales-page-ginza.png', fullPage: true });
  console.log('\n📸 スクリーンショット保存: sales-page-ginza.png');
});
