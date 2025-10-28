import { test, expect } from '@playwright/test';

test('予約909がサブスクとして表示されるか確認', async ({ page }) => {
  // ログイン
  await page.goto('http://localhost:8000/admin/login');
  await page.fill('input[type="email"]', 'naoki@yumeno-marketing.jp');
  await page.fill('input[type="password"]', 'Takahashi5000');
  await page.click('button[type="submit"]');
  await page.waitForURL('**/admin', { timeout: 10000 });

  // 日次精算ページへ移動
  await page.goto('http://localhost:8000/admin/sales/daily-closing');
  await page.waitForTimeout(2000);

  console.log('\n📊 === 予約909の種別確認 ===');

  // 日付を2025-10-29に設定（予約909の日付）
  await page.fill('input[type="date"]', '2025-10-29');
  await page.waitForTimeout(2000);

  // 予約909を探す
  const tableBody = await page.locator('table tbody');
  const rows = await tableBody.locator('tr').all();

  console.log(`  表示されている予約数: ${rows.length}件`);

  for (const row of rows) {
    const cells = await row.locator('td').all();
    if (cells.length >= 4) {
      const timeText = await cells[0].textContent();
      const customerText = await cells[1].textContent();
      const menuText = await cells[2].textContent();
      const typeCell = cells[3];
      const typeText = await typeCell.textContent();
      const badge = await typeCell.locator('span').first();
      const badgeClass = await badge.getAttribute('class');

      // 予約909の情報をログ出力（時間: 19:15、メニュー: 眼精疲労ケア1年50分コース）
      if (timeText.includes('19:15') && menuText.includes('眼精疲労ケア1年50分コース')) {
        console.log(`\n  時間: ${timeText.trim()}`);
        console.log(`  顧客: ${customerText.trim()}`);
        console.log(`  メニュー: ${menuText.trim()}`);
        console.log(`  種別: ${typeText.trim()}`);
        console.log(`  バッジクラス: ${badgeClass}`);

        // サブスクとして表示されているか確認
        if (typeText.includes('サブスク')) {
          console.log(`  ✅ 正しくサブスクとして表示されています`);
        } else {
          console.log(`  ❌ スポットとして表示されています（修正必要）`);
        }
      }
    }
  }

  console.log('\n');
});
