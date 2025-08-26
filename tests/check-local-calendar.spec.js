import { test, expect } from '@playwright/test';

test('Check local calendar time slots', async ({ page }) => {
  // ローカル環境にアクセス
  await page.goto('http://127.0.0.1:8000/stores');
  
  // デバッグ用にスクリーンショット
  await page.screenshot({ path: 'test-results/1-stores-page.png' });
  
  // 店舗選択
  await page.waitForSelector('[id="stores-container"]', { timeout: 10000 });
  await page.locator('button:has-text("この店舗を選択")').first().click();
  
  // メニュー選択画面
  await page.waitForURL('**/reservation/menu');
  await page.screenshot({ path: 'test-results/2-menu-page.png' });
  
  await page.waitForSelector('#menus-container', { timeout: 10000 });
  await page.locator('button:has-text("このメニューを選択")').first().click();
  
  // 日時選択画面
  await page.waitForURL('**/reservation/datetime');
  await page.waitForSelector('.calendar-day', { timeout: 10000 });
  
  // 明日を選択
  const tomorrow = new Date();
  tomorrow.setDate(tomorrow.getDate() + 1);
  const tomorrowDay = tomorrow.getDate();
  
  const dayElements = await page.$$('.calendar-day');
  for (const element of dayElements) {
    const text = await element.textContent();
    if (text === tomorrowDay.toString()) {
      const classes = await element.getAttribute('class');
      if (!classes?.includes('text-gray-400')) {
        await element.click();
        break;
      }
    }
  }
  
  // 時間スロットが表示されるまで待つ
  await page.waitForSelector('#time-slots-container', { timeout: 10000 });
  await page.waitForTimeout(2000); // APIレスポンスを待つ
  
  // スクリーンショットを取る
  await page.screenshot({ 
    path: 'test-results/3-time-slots.png',
    fullPage: true 
  });
  
  // 時間スロットを取得
  const timeSlots = await page.$$eval('.time-slot', slots => 
    slots.map(slot => slot.textContent?.trim())
  );
  
  console.log('Found time slots:', timeSlots);
  console.log('Total slots:', timeSlots.length);
  console.log('Last slot:', timeSlots[timeSlots.length - 1]);
  
  // APIレスポンスも確認
  const apiResponse = await page.evaluate(async () => {
    const selectedStore = JSON.parse(sessionStorage.getItem('selectedStore') || '{}');
    const selectedMenu = JSON.parse(sessionStorage.getItem('selectedMenu') || '{}');
    const tomorrow = new Date();
    tomorrow.setDate(tomorrow.getDate() + 1);
    const dateStr = tomorrow.toISOString().split('T')[0];
    
    const response = await fetch(`/api/availability/slots?store_id=${selectedStore.id}&menu_id=${selectedMenu.id}&date=${dateStr}`);
    const data = await response.json();
    return {
      total: data.available_slots?.length || 0,
      first: data.available_slots?.[0]?.time,
      last: data.available_slots?.[data.available_slots.length - 1]?.time,
      businessHours: data.business_hours
    };
  });
  
  console.log('API Response:', apiResponse);
});