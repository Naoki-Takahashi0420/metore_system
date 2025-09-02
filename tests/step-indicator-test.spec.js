import { test, expect } from '@playwright/test';

test('ステップインジケーターの統一性確認', async ({ page }) => {
  // モバイルサイズでテスト
  await page.setViewportSize({ width: 390, height: 844 });
  
  // 1. 店舗選択画面
  await page.goto('http://127.0.0.1:8000/reservation/store');
  await page.waitForTimeout(1000);
  
  // モバイルインジケーターの確認
  const step1Mobile = await page.locator('.block.sm\\:hidden .w-8.h-8').count();
  console.log('Step 1 - モバイルインジケーター数:', step1Mobile);
  expect(step1Mobile).toBe(4);
  
  // ステップ1がアクティブか確認
  const step1Active = await page.locator('.block.sm\\:hidden .bg-blue-500').first().textContent();
  console.log('Step 1 - アクティブステップ:', step1Active);
  expect(step1Active).toBe('1');
  
  await page.screenshot({ path: 'step1-mobile.png' });
  
  // 2. カテゴリー選択画面へ
  await page.locator('[onclick*="selectStore"]').first().click();
  await page.waitForTimeout(1000);
  
  const step2Mobile = await page.locator('.block.sm\\:hidden .w-8.h-8').count();
  console.log('Step 2 - モバイルインジケーター数:', step2Mobile);
  expect(step2Mobile).toBe(4);
  
  const step2Active = await page.locator('.block.sm\\:hidden .bg-blue-500').first().textContent();
  console.log('Step 2 - アクティブステップ:', step2Active);
  expect(step2Active).toBe('2');
  
  await page.screenshot({ path: 'step2-mobile.png' });
  
  // PC版も確認
  await page.setViewportSize({ width: 1024, height: 768 });
  await page.waitForTimeout(500);
  
  // PC版のステップ数確認
  const step2PC = await page.locator('.hidden.sm\\:block .rounded-full.h-12.w-12').count();
  console.log('Step 2 - PCインジケーター数:', step2PC);
  expect(step2PC).toBe(4);
  
  await page.screenshot({ path: 'step2-pc.png' });
});