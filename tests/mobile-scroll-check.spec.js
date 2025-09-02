import { test, expect } from '@playwright/test';

test('モバイル横スクロール確認', async ({ page }) => {
  // iPhone 12のビューポートサイズ
  await page.setViewportSize({ width: 390, height: 844 });
  
  // 店舗選択画面
  await page.goto('http://127.0.0.1:8000/reservation/store');
  await page.waitForTimeout(1000);
  
  // 横スクロールチェック
  const hasHorizontalScrollStore = await page.evaluate(() => {
    return document.documentElement.scrollWidth > document.documentElement.clientWidth;
  });
  console.log('店舗選択画面 - 横スクロール:', hasHorizontalScrollStore);
  
  // 店舗選択
  await page.locator('[onclick*="selectStore"]').first().click();
  await page.waitForTimeout(1000);
  
  // カテゴリー選択画面での横スクロールチェック
  const hasHorizontalScrollCategory = await page.evaluate(() => {
    return document.documentElement.scrollWidth > document.documentElement.clientWidth;
  });
  console.log('カテゴリー選択画面 - 横スクロール:', hasHorizontalScrollCategory);
  
  // スクリーンショット
  await page.screenshot({ path: 'mobile-category-screen.png', fullPage: true });
  
  // アサーション
  expect(hasHorizontalScrollStore).toBe(false);
  expect(hasHorizontalScrollCategory).toBe(false);
});