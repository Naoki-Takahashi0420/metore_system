import { test, expect } from '@playwright/test';

test('管理画面のカレンダーウィジェットに顧客名が表示される', async ({ page }) => {
  console.log('テスト開始: カレンダーウィジェット確認');
  // 管理画面にログイン
  await page.goto('http://localhost:8000/admin/login');

  // ログインフォームの要素を待つ
  await page.waitForSelector('input[name="email"]', { timeout: 10000 });
  await page.fill('input[name="email"]', 'admin@eye-training.com');
  await page.fill('input[name="password"]', 'password');
  await page.click('button[type="submit"]');

  // ダッシュボードが表示されるまで待つ
  await page.waitForURL('**/admin', { timeout: 15000 });

  // ページが完全に読み込まれるまで待つ
  await page.waitForLoadState('networkidle');
  await page.waitForTimeout(3000);

  // カレンダーウィジェットが表示されるのを待つ
  const calendar = await page.locator('.fc-daygrid').first();
  await expect(calendar).toBeVisible({ timeout: 15000 });

  // カレンダーイベントの存在を確認
  await page.waitForSelector('.fc-event', { timeout: 10000 });
  const events = await page.locator('.fc-event').all();

  console.log(`見つかったイベント数: ${events.length}`);
  expect(events.length).toBeGreaterThan(0);

  // 最初のイベントの内容を確認
  const firstEvent = events[0];
  const eventText = await firstEvent.textContent();
  console.log('カレンダーイベントの表示内容:', eventText);

  // 期待する内容の確認
  // - 件数が表示されている（例: "3件"）
  expect(eventText).toMatch(/\d+件/);

  // - 顧客名が表示されている（例: "10:00 山田様"）
  expect(eventText).toMatch(/\d{1,2}:\d{2}.*様/);

  // 今日の日付セルを探す
  const today = new Date();
  const todayNumber = today.getDate();
  const todayCell = page.locator(`.fc-daygrid-day[data-date*="${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}-${String(todayNumber).padStart(2, '0')}"]`);

  if (await todayCell.count() > 0) {
    const todayEvents = await todayCell.locator('.fc-event').all();
    console.log(`今日のイベント数: ${todayEvents.length}`);

    if (todayEvents.length > 0) {
      const todayEventText = await todayEvents[0].textContent();
      console.log('今日のイベント内容:', todayEventText);

      // 今日のイベントが複数の顧客を表示しているか確認
      // 例: "🟢 3件\n10:00 相嶋様、14:00 明石様、他1名"
      expect(todayEventText).toMatch(/🟢|🟡|🟠|🔴|🔥/); // 絵文字確認
    }
  }

  // スクリーンショットを撮る
  await page.screenshot({ path: 'calendar-widget-fixed.png', fullPage: true });

  // 各イベントの内容を出力
  for (let i = 0; i < Math.min(events.length, 5); i++) {
    const event = events[i];
    const text = await event.textContent();
    console.log(`イベント ${i + 1}: ${text}`);
  }
});