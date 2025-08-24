import { test, expect } from '@playwright/test';

test('予約可能時間枠の詳細確認', async ({ page }) => {
  console.log('\n========================================');
  console.log('📅 予約可能時間枠デバッグ');
  console.log('========================================\n');
  
  // メニュー選択
  await page.goto('http://127.0.0.1:8000/reservation/menu');
  await page.locator('.bg-white.rounded-lg.shadow-sm').first().click();
  await page.waitForURL('**/reservation');
  
  // 時間枠の詳細を確認
  const table = page.locator('table.availability-table');
  await expect(table).toBeVisible();
  
  // 曜日ヘッダーを取得
  const headers = await page.locator('thead th').allTextContents();
  console.log('📅 曜日ヘッダー:');
  headers.forEach((header, i) => {
    if (i > 0 && header.trim()) {
      console.log(`  ${i}. ${header.replace(/\s+/g, ' ').trim()}`);
    }
  });
  
  // 各時間帯の状況を確認
  const timeRows = await page.locator('tbody tr').all();
  console.log(`\n⏰ 時間帯数: ${timeRows.length}`);
  
  // 最初の3つの時間帯を詳しく確認
  for (let i = 0; i < Math.min(3, timeRows.length); i++) {
    const row = timeRows[i];
    const timeLabel = await row.locator('td').first().textContent();
    console.log(`\n時間帯 ${i+1}: ${timeLabel?.trim()}`);
    
    const cells = await row.locator('td').all();
    for (let j = 1; j < Math.min(4, cells.length); j++) {
      const cell = cells[j];
      const hasAvailableSlot = await cell.locator('button.time-slot').count() > 0;
      const hasUnavailable = await cell.locator('span.text-gray-400').count() > 0;
      
      if (hasAvailableSlot) {
        const button = cell.locator('button.time-slot').first();
        const date = await button.getAttribute('data-date');
        const time = await button.getAttribute('data-time');
        console.log(`  列${j}: ✅ 予約可能 (${date} ${time})`);
      } else if (hasUnavailable) {
        console.log(`  列${j}: ❌ 予約不可`);
      }
    }
  }
  
  // 全体の統計
  const availableCount = await page.locator('button.time-slot').count();
  const unavailableCount = await page.locator('span.text-gray-400.text-xl').count();
  
  console.log('\n📊 統計:');
  console.log(`  予約可能: ${availableCount}個`);
  console.log(`  予約不可: ${unavailableCount}個`);
  console.log(`  合計: ${availableCount + unavailableCount}個`);
  
  // 店舗IDを確認
  const storeSelect = page.locator('#storeSelect');
  const storeId = await storeSelect.inputValue();
  console.log(`\n🏢 選択中の店舗ID: ${storeId}`);
  
  // 現在の日付を確認
  const today = new Date();
  console.log(`\n📅 今日の日付: ${today.toLocaleDateString('ja-JP')}`);
  console.log(`  曜日: ${['日', '月', '火', '水', '木', '金', '土'][today.getDay()]}曜日`);
  
  // もし土曜日の場合、次週へ移動してみる
  if (availableCount === 0 && today.getDay() === 6) {
    console.log('\n🔄 土曜日で予約枠がないため、次週を確認...');
    
    const nextWeekButton = page.locator('a').filter({ hasText: '次の一週間' });
    if (await nextWeekButton.isVisible()) {
      await nextWeekButton.click();
      await page.waitForTimeout(1000);
      
      const nextWeekAvailable = await page.locator('button.time-slot').count();
      console.log(`  次週の予約可能枠: ${nextWeekAvailable}個`);
    }
  }
  
  // スクリーンショットを保存
  await page.screenshot({ path: 'availability-debug.png', fullPage: true });
  console.log('\n📸 スクリーンショット保存: availability-debug.png');
});