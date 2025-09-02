import { test, expect } from '@playwright/test';

test('モバイル時間選択画面の表示確認', async ({ page }) => {
  // iPhone 12のビューポートサイズ
  await page.setViewportSize({ width: 390, height: 844 });
  
  // 予約フローの開始
  await page.goto('http://127.0.0.1:8000/reserve');
  await page.waitForTimeout(1000);
  
  // 店舗選択（最初の店舗をクリック）
  const storeCard = page.locator('.store-card').first();
  await storeCard.click();
  await page.waitForTimeout(1000);
  
  // カテゴリー選択（最初のカテゴリーをクリック）
  const categoryCard = page.locator('.category-card').first();
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
  await page.screenshot({ path: 'mobile-time-flow.png', fullPage: true });
  
  // ステップインジケーターの表示確認
  const stepIndicator = await page.locator('.block.sm\\:hidden').first();
  const stepIndicatorBox = await stepIndicator.boundingBox();
  console.log('ステップインジケーター幅:', stepIndicatorBox?.width);
  
  // タブレット表示も確認
  await page.setViewportSize({ width: 768, height: 1024 });
  await page.waitForTimeout(1000);
  await page.screenshot({ path: 'tablet-time-flow.png', fullPage: true });
  
  // タブレットでの横スクロールチェック
  const hasHorizontalScrollTablet = await page.evaluate(() => {
    return document.documentElement.scrollWidth > document.documentElement.clientWidth;
  });
  
  console.log('タブレット横スクロール発生:', hasHorizontalScrollTablet);
  
  // アサーション
  expect(hasHorizontalScroll).toBe(false);
  expect(hasHorizontalScrollTablet).toBe(false);
});