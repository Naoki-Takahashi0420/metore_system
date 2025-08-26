import { test, expect } from '@playwright/test';

test('Reservation time slots show until 23:00', async ({ page }) => {
  // 本番サイトにアクセス
  await page.goto('https://reservation.meno-training.com/stores');
  
  // 店舗選択
  await page.waitForSelector('[id="stores-container"]', { timeout: 10000 });
  // 最初の選択ボタンをクリック
  await page.locator('button:has-text("この店舗を選択")').first().click();
  
  // メニュー選択画面
  await page.waitForURL('**/reservation/menu');
  await page.waitForSelector('button:has-text("このメニューを選択")', { timeout: 10000 });
  await page.click('button:has-text("このメニューを選択")');
  
  // 日時選択画面
  await page.waitForURL('**/reservation/datetime');
  await page.waitForSelector('.calendar-day', { timeout: 10000 });
  
  // カレンダーの明日をクリック
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
  await page.waitForSelector('.time-slot', { timeout: 10000 });
  
  // スクリーンショットを取る
  await page.screenshot({ 
    path: 'test-results/reservation-time-slots.png',
    fullPage: true 
  });
  
  // 最後の時間スロットをチェック
  const timeSlots = await page.$$('.time-slot');
  const lastSlots = [];
  
  // 最後の3つのスロットを取得
  for (let i = Math.max(0, timeSlots.length - 3); i < timeSlots.length; i++) {
    const slotText = await timeSlots[i].textContent();
    lastSlots.push(slotText?.trim());
  }
  
  console.log('Last time slots:', lastSlots);
  
  // 23:00があるか確認
  const hasLateSlot = lastSlots.some(slot => 
    slot?.includes('22:30') || slot?.includes('23:00')
  );
  
  expect(hasLateSlot).toBeTruthy();
});