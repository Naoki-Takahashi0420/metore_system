import { test, expect } from '@playwright/test';

test('Verify calendar vertical display is working', async ({ page }) => {
    // ログイン
    await page.goto('http://localhost:8000/admin/login');
    await page.fill('input[type="email"]', 'admin@eye-training.com');
    await page.fill('input[type="password"]', 'password');
    await page.click('button[type="submit"]');
    await page.waitForURL('**/admin');

    // カレンダーウィジェットが表示されるまで待つ
    await page.waitForSelector('.fc-daygrid-day-events', { timeout: 10000 });

    // eventDidMountが実行されるまで少し待つ
    await page.waitForTimeout(2000);

    // カレンダーのイベントを取得
    const events = await page.locator('.fc-event').all();
    console.log(`Found ${events.length} events`);

    let hasVerticalDisplay = false;

    for (const event of events) {
        // イベントのHTML構造を取得
        const titleElement = await event.locator('.fc-event-title').first();
        const innerHTML = await titleElement.innerHTML();
        const textContent = await titleElement.textContent();

        console.log('Event HTML:', innerHTML);
        console.log('Event Text:', textContent);

        // divタグが含まれているか確認（縦書き表示の証拠）
        if (innerHTML.includes('<div')) {
            hasVerticalDisplay = true;
            console.log('✅ Vertical display detected with div tags');

            // 各divの内容を確認
            const divElements = await titleElement.locator('div').all();
            console.log(`  Found ${divElements.length} div elements`);

            for (const div of divElements) {
                const divText = await div.textContent();
                console.log(`  - ${divText}`);
            }
        } else if (textContent && textContent.includes('\n')) {
            console.log('⚠️  Line breaks found but not rendered as divs');
        } else {
            console.log('❌ No vertical display detected');
        }
    }

    // スクリーンショットを撮る
    await page.screenshot({ path: 'calendar-vertical-check.png', fullPage: true });

    // 結果を出力
    if (hasVerticalDisplay) {
        console.log('\n✅ SUCCESS: Calendar is displaying customer names vertically');
    } else {
        console.log('\n❌ FAILURE: Calendar is NOT displaying customer names vertically');
        console.log('The eventDidMount function may not be working properly.');
        console.log('HTML tags might be escaped by FullCalendar.');
    }

    expect(hasVerticalDisplay).toBe(true);
});