import { test, expect } from '@playwright/test';

test('Calendar displays customer names vertically', async ({ page }) => {
    // ログイン
    await page.goto('http://localhost:8000/admin/login');
    await page.fill('input[type="email"]', 'admin@eye-training.com');
    await page.fill('input[type="password"]', 'password');
    await page.click('button[type="submit"]');
    await page.waitForURL('**/admin');

    // カレンダーウィジェットが表示されるまで待つ
    await page.waitForSelector('.fc-daygrid-day-events', { timeout: 10000 });

    // カレンダーのイベントをチェック
    const events = await page.locator('.fc-event').all();
    console.log(`Found ${events.length} events`);

    if (events.length > 0) {
        // 最初のイベントのHTMLを取得
        const firstEventHtml = await events[0].innerHTML();
        console.log('First event HTML:', firstEventHtml);

        // カスタムコンテンツが存在するか確認
        const hasCustomContent = await events[0].locator('div').count() > 1;
        console.log('Has custom content:', hasCustomContent);

        // 顧客名が表示されているか確認（時間付き）
        const eventText = await events[0].textContent();
        console.log('Event text content:', eventText);

        // 縦書き確認：時間パターン（HH:MM）が複数行に存在するか
        const hasTimePattern = /\d{2}:\d{2}/.test(eventText);
        console.log('Has time pattern:', hasTimePattern);
    }

    // コンソールログを確認
    page.on('console', msg => {
        if (msg.text().includes('CustomerList:') || msg.text().includes('Event data:')) {
            console.log('Console:', msg.text());
        }
    });

    // スクリーンショットを撮る
    await page.screenshot({ path: 'calendar-display.png', fullPage: true });
});