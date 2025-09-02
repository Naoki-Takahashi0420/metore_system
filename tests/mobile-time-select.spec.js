import { test } from '@playwright/test';

test('モバイル表示の確認', async ({ page }) => {
  // iPhone 12のビューポートサイズ
  await page.setViewportSize({ width: 390, height: 844 });
  
  // 直接予約フローをシミュレート
  await page.goto('http://127.0.0.1:8000/reserve');
  
  // 店舗選択
  await page.click('text=銀座本店');
  
  // カテゴリー選択
  await page.waitForTimeout(1000);
  await page.click('text=ケアコース');
  
  // 時間選択画面でスクリーンショット
  await page.waitForTimeout(2000);
  await page.screenshot({ path: 'mobile-time-select.png', fullPage: true });
  
  // タブレット表示も確認
  await page.setViewportSize({ width: 768, height: 1024 });
  await page.screenshot({ path: 'tablet-time-select.png', fullPage: true });
});