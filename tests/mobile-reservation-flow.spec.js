import { test, expect } from '@playwright/test';

test('モバイル時間選択画面の表示確認（正しいフロー）', async ({ page }) => {
  // iPhone 12のビューポートサイズ
  await page.setViewportSize({ width: 390, height: 844 });
  
  // 予約フローの開始（正しいURL）
  await page.goto('http://127.0.0.1:8000/reservation/store');
  await page.waitForTimeout(1000);
  
  // 店舗選択（最初の店舗をクリック）
  const storeCard = page.locator('[onclick*="selectStore"]').first();
  await storeCard.click();
  await page.waitForTimeout(1000);
  
  // カテゴリー選択（最初のカテゴリーをクリック）
  const categoryCard = page.locator('[onclick*="selectCategory"]').first();
  await categoryCard.click();
  await page.waitForTimeout(2000);
  
  // 時間選択画面でのチェック
  console.log('Current URL:', await page.url());
  
  // 横スクロールのチェック
  const hasHorizontalScroll = await page.evaluate(() => {
    return document.documentElement.scrollWidth > document.documentElement.clientWidth;
  });
  
  console.log('モバイル横スクロール発生:', hasHorizontalScroll);
  
  // スクリーンショット撮影
  await page.screenshot({ path: 'mobile-reservation-flow.png', fullPage: true });
  
  // ステップインジケーターの表示確認
  const stepIndicatorMobile = await page.locator('.block.sm\\:hidden').isVisible();
  console.log('モバイルステップインジケーター表示:', stepIndicatorMobile);
  
  // タブレット表示も確認
  await page.setViewportSize({ width: 768, height: 1024 });
  await page.waitForTimeout(1000);
  
  // タブレットでの横スクロールチェック
  const hasHorizontalScrollTablet = await page.evaluate(() => {
    return document.documentElement.scrollWidth > document.documentElement.clientWidth;
  });
  
  console.log('タブレット横スクロール発生:', hasHorizontalScrollTablet);
  await page.screenshot({ path: 'tablet-reservation-flow.png', fullPage: true });
  
  // PC版ステップインジケーターの表示確認
  const stepIndicatorPC = await page.locator('.hidden.sm\\:block').isVisible();
  console.log('PCステップインジケーター表示:', stepIndicatorPC);
  
  // アサーション
  expect(hasHorizontalScroll).toBe(false);
  expect(hasHorizontalScrollTablet).toBe(false);
});