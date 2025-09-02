import { test, expect } from '@playwright/test';

test('テスト用カレンダー表示確認', async ({ page }) => {
  // テスト用ルートでカレンダーページへ
  await page.goto('http://127.0.0.1:8000/test-calendar');
  await page.waitForTimeout(2000);
  
  // URLを確認
  const url = page.url();
  console.log('Current URL:', url);
  
  // ページタイトルや内容を確認
  const title = await page.textContent('h1');
  console.log('Page title:', title);
  
  // テーブル内の○と×を探す
  const circles = await page.locator('td:has-text("○")').count();
  const crosses = await page.locator('td:has-text("×")').count();
  
  console.log('Circles (○) found:', circles);
  console.log('Crosses (×) found:', crosses);
  
  // time-slotクラスを持つ要素を探す
  const timeSlots = await page.locator('.time-slot').count();
  console.log('Time slots found:', timeSlots);
  
  // スクリーンショット
  await page.screenshot({ path: 'test-calendar.png', fullPage: true });
  
  // 少なくともいくつかのスロットが表示されているはず
  expect(circles + crosses + timeSlots).toBeGreaterThan(0);
});