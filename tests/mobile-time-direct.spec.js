import { test, expect } from '@playwright/test';

test('モバイル表示の確認（直接アクセス）', async ({ page }) => {
  // iPhone 12のビューポートサイズ
  await page.setViewportSize({ width: 390, height: 844 });
  
  // 直接時間選択画面へアクセス（セッションをセット）
  await page.goto('http://127.0.0.1:8000/reservation/time');
  
  // ページが読み込まれるまで待機
  await page.waitForTimeout(2000);
  
  // スクリーンショット撮影
  await page.screenshot({ path: 'mobile-time-select-direct.png', fullPage: true });
  
  // 横スクロールのチェック
  const hasHorizontalScroll = await page.evaluate(() => {
    return document.documentElement.scrollWidth > document.documentElement.clientWidth;
  });
  
  console.log('横スクロール発生:', hasHorizontalScroll);
  
  // タブレット表示も確認
  await page.setViewportSize({ width: 768, height: 1024 });
  await page.waitForTimeout(1000);
  await page.screenshot({ path: 'tablet-time-select-direct.png', fullPage: true });
  
  // タブレットでの横スクロールチェック
  const hasHorizontalScrollTablet = await page.evaluate(() => {
    return document.documentElement.scrollWidth > document.documentElement.clientWidth;
  });
  
  console.log('タブレット横スクロール発生:', hasHorizontalScrollTablet);
  
  // アサーション
  expect(hasHorizontalScroll).toBe(false);
  expect(hasHorizontalScrollTablet).toBe(false);
});