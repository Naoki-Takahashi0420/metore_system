import { test, expect } from '@playwright/test';

test('管理画面カレンダー表示確認', async ({ page }) => {
  // 管理画面にログイン
  console.log('1. 管理画面へアクセス');
  await page.goto('http://localhost:8000/admin/login', { waitUntil: 'networkidle' });

  // スクリーンショット
  await page.screenshot({ path: 'test-login-page.png' });

  // ログインフォーム確認
  console.log('2. ログインフォーム確認');
  const emailField = await page.locator('input[type="email"], input[name="email"], #email').first();
  const passwordField = await page.locator('input[type="password"], input[name="password"], #password').first();

  // 要素の存在確認
  console.log('Email field exists:', await emailField.count() > 0);
  console.log('Password field exists:', await passwordField.count() > 0);

  // ログイン試行
  console.log('3. ログイン実行');
  await emailField.fill('admin@eye-training.com');
  await passwordField.fill('password');

  // フォーム送信
  const submitButton = await page.locator('button[type="submit"], button:has-text("ログイン"), button:has-text("Sign in")').first();
  await submitButton.click();

  // ダッシュボードへの遷移を待つ
  console.log('4. ダッシュボード遷移待ち');
  await page.waitForURL('**/admin', { timeout: 30000 });

  // ダッシュボード読み込み完了を待つ
  console.log('5. ページ読み込み待ち');
  await page.waitForLoadState('networkidle');
  await page.waitForTimeout(5000);

  // スクリーンショット
  await page.screenshot({ path: 'test-dashboard.png', fullPage: true });

  // カレンダーウィジェット確認
  console.log('6. カレンダーウィジェット確認');

  // FullCalendarの要素を探す
  const calendarSelectors = [
    '.fc-daygrid',
    '.fc-view',
    '.fc-scrollgrid',
    '[id*="calendar"]',
    '.filament-fullcalendar'
  ];

  let calendarFound = false;
  for (const selector of calendarSelectors) {
    const count = await page.locator(selector).count();
    console.log(`Selector ${selector}: ${count} elements found`);
    if (count > 0) {
      calendarFound = true;
      break;
    }
  }

  expect(calendarFound).toBeTruthy();

  // イベント要素を探す
  console.log('7. カレンダーイベント確認');
  const eventSelectors = [
    '.fc-event',
    '.fc-daygrid-event',
    '.fc-h-event',
    '.fc-daygrid-day-events',
    'a[class*="fc-event"]'
  ];

  let eventsFound = false;
  let eventContent = null;
  for (const selector of eventSelectors) {
    const events = await page.locator(selector).all();
    console.log(`Event selector ${selector}: ${events.length} events found`);

    if (events.length > 0) {
      eventsFound = true;
      // 最初のイベントのテキストを取得
      eventContent = await events[0].textContent();
      console.log(`First event content: ${eventContent}`);
      break;
    }
  }

  // カレンダー全体のHTMLを確認
  const calendarHTML = await page.locator('.fc-view, .fc-daygrid').first().innerHTML();
  console.log('Calendar HTML preview:', calendarHTML.substring(0, 500));

  // 最終スクリーンショット
  await page.screenshot({ path: 'test-calendar-final.png', fullPage: true });

  // アサーション
  expect(eventsFound).toBeTruthy();

  if (eventContent) {
    console.log('イベント内容:', eventContent);
    // 件数表示の確認
    expect(eventContent).toMatch(/\d+件/);
  }
});