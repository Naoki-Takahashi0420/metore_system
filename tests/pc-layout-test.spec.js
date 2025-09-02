import { test, expect } from '@playwright/test';

test('PC画面のレイアウト確認', async ({ page }) => {
  // PC画面サイズ
  await page.setViewportSize({ width: 1920, height: 1080 });
  
  // 1. 店舗選択画面
  await page.goto('http://127.0.0.1:8000/stores');
  await page.waitForTimeout(2000);
  await page.screenshot({ path: 'pc-stores.png', fullPage: false });
  
  // 2. 予約フローの店舗選択
  await page.goto('http://127.0.0.1:8000/reservation/store');
  await page.waitForTimeout(1000);
  await page.screenshot({ path: 'pc-reservation-store.png', fullPage: false });
  
  // 3. カテゴリー選択
  await page.locator('[onclick*="selectStore"]').first().click();
  await page.waitForTimeout(1000);
  await page.screenshot({ path: 'pc-category.png', fullPage: false });
});