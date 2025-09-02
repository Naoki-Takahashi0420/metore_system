import { test, expect } from '@playwright/test';

test('カレンダー予約可能時間の表示確認', async ({ page }) => {
  // 予約フローをシミュレート
  await page.goto('http://127.0.0.1:8000/reservation/store');
  await page.waitForTimeout(1000);
  
  // 店舗選択
  await page.locator('[onclick*="selectStore"]').first().click();
  await page.waitForTimeout(1000);
  
  // カテゴリー選択
  await page.locator('button[type="submit"]').first().click();
  await page.waitForTimeout(1000);
  
  // メニュー選択
  await page.locator('button:has-text("予約する")').first().click();
  await page.waitForTimeout(1000);
  
  // カレンダーページに到達
  await page.waitForURL('**/reservation/calendar');
  
  // ○と×の数を確認
  const availableSlots = await page.locator('text=○').count();
  const unavailableSlots = await page.locator('text=×').count();
  
  console.log('Available slots (○):', availableSlots);
  console.log('Unavailable slots (×):', unavailableSlots);
  
  await page.screenshot({ path: 'calendar-availability.png', fullPage: true });
  
  // 少なくともいくつかの○があるはず
  expect(availableSlots).toBeGreaterThan(0);
});