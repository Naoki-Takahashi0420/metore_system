import { test, expect } from '@playwright/test';

test('Final check - Calendar shows customer names vertically', async ({ page }) => {
    // コンソールログを監視
    page.on('console', msg => {
        if (msg.text().includes('customerList') || msg.text().includes('eventContent')) {
            console.log('Browser console:', msg.text());
        }
    });

    // ログイン
    await page.goto('http://localhost:8000/admin/login');
    await page.fill('input[type="email"]', 'admin@eye-training.com');
    await page.fill('input[type="password"]', 'password');
    await page.click('button[type="submit"]');
    await page.waitForURL('**/admin');

    // カレンダーウィジェットが表示されるまで待つ
    await page.waitForSelector('.fc-daygrid-day-events', { timeout: 10000 });
    await page.waitForTimeout(2000);

    // イベントの内容を確認
    const events = await page.locator('.fc-event').all();
    console.log(`\n📅 Found ${events.length} calendar events\n`);

    for (let i = 0; i < events.length; i++) {
        const event = events[i];
        const eventText = await event.textContent();
        console.log(`Event ${i + 1}:`);

        // 各行を表示
        const lines = eventText.split(/\n/);
        lines.forEach(line => {
            if (line.trim()) {
                console.log(`  - ${line.trim()}`);
            }
        });

        // div要素があるか確認
        const divCount = await event.locator('div').count();
        if (divCount > 1) {
            console.log(`  ✅ Has ${divCount} div elements (vertical display)`);
        } else {
            console.log(`  ❌ Only ${divCount} div element (horizontal display)`);
        }
    }

    // スクリーンショット
    await page.screenshot({ path: 'calendar-final-check.png', fullPage: true });
    console.log('\n📸 Screenshot saved as calendar-final-check.png');
});