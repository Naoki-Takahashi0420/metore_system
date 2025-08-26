import { test } from '@playwright/test';

test('Direct access to datetime page', async ({ page }) => {
  // 直接日時選択ページにアクセス（セッションストレージを設定）
  await page.goto('http://127.0.0.1:8000/reservation/datetime');
  
  // セッションストレージに必要なデータを設定
  await page.evaluate(() => {
    sessionStorage.setItem('selectedStore', JSON.stringify({
      id: 1,
      name: '目のトレーニング 東京本店'
    }));
    sessionStorage.setItem('selectedMenu', JSON.stringify({
      id: 1,
      name: '眼精疲労ケアコース',
      price: 5000,
      duration: 60
    }));
  });
  
  // ページをリロード
  await page.reload();
  
  // カレンダーが表示されるまで待つ
  await page.waitForSelector('.calendar-day', { timeout: 10000 });
  
  // 明日を選択
  const tomorrow = new Date();
  tomorrow.setDate(tomorrow.getDate() + 1);
  const tomorrowDay = tomorrow.getDate();
  
  // 日付をクリック
  await page.click(`.calendar-day:has-text("${tomorrowDay}")`);
  
  // 時間スロットが表示されるまで待つ
  await page.waitForSelector('.time-slot', { timeout: 10000 });
  
  // APIレスポンスを確認
  const apiData = await page.evaluate(() => {
    return window._lastApiResponse || 'No API response captured';
  });
  console.log('API Response:', apiData);
  
  // 時間スロットを取得
  const timeSlots = await page.$$eval('.time-slot', slots => 
    slots.map(slot => slot.textContent?.trim())
  );
  
  console.log('表示されている時間スロット:');
  console.log('Total:', timeSlots.length);
  console.log('First 5:', timeSlots.slice(0, 5));
  console.log('Last 5:', timeSlots.slice(-5));
  
  // スクリーンショット
  await page.screenshot({ 
    path: 'test-results/datetime-page.png',
    fullPage: true 
  });
  
  // ブラウザを開いたまま待つ
  await page.waitForTimeout(30000);
});