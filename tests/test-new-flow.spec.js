import { test, expect } from '@playwright/test';

test('Test new reservation flow - check 23:30 slots', async ({ page }) => {
  // 1. 店舗選択から開始
  await page.goto('http://127.0.0.1:8000/stores');
  console.log('Step 1: 店舗選択ページ');
  
  await page.waitForSelector('#stores-container', { timeout: 10000 });
  await page.screenshot({ path: 'test-results/1-stores.png' });
  
  // 最初の店舗を選択
  await page.click('button:has-text("この店舗を選択")');
  
  // 2. メニュー選択画面（/reservation/menu）
  await page.waitForURL('**/reservation/menu');
  console.log('Step 2: メニュー選択ページ');
  await page.waitForTimeout(2000);
  await page.screenshot({ path: 'test-results/2-menu.png' });
  
  // 最初のメニューを選択
  await page.click('button:has-text("このメニューを選択")');
  
  // 3. ○×形式の日時選択画面
  console.log('Step 3: 日時選択ページ（○×形式）');
  await page.waitForTimeout(3000);
  await page.screenshot({ path: 'test-results/3-datetime-grid.png', fullPage: true });
  
  // 時間スロットを確認
  const timeSlotElements = await page.$$('.availability-slot, .time-slot, [class*="slot"]');
  console.log('Found slot elements:', timeSlotElements.length);
  
  // ページのHTMLを確認
  const pageContent = await page.content();
  
  // 23:00や23:30が含まれているかチェック
  const has2300 = pageContent.includes('23:00');
  const has2330 = pageContent.includes('23:30');
  const has1730 = pageContent.includes('17:30');
  const has1800 = pageContent.includes('18:00');
  
  console.log('時間チェック:');
  console.log('17:30あり:', has1730);
  console.log('18:00あり:', has1800);
  console.log('23:00あり:', has2300);
  console.log('23:30あり:', has2330);
  
  // デバッグ用にHTMLの一部を出力
  const bodyText = await page.locator('body').innerText();
  const timeMatches = bodyText.match(/\d{1,2}:\d{2}/g);
  if (timeMatches) {
    const uniqueTimes = [...new Set(timeMatches)].sort();
    console.log('ページ内の全時間:', uniqueTimes);
    console.log('最後の5つの時間:', uniqueTimes.slice(-5));
  }
  
  // 10秒待機してブラウザで確認
  await page.waitForTimeout(10000);
});