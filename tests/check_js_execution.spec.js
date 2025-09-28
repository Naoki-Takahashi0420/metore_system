import { test, expect } from '@playwright/test';

test('Check if JavaScript is executing and apply manual fix', async ({ page }) => {
    // コンソールログを監視
    page.on('console', msg => {
        console.log('Browser console:', msg.text());
    });

    // ログイン
    await page.goto('http://localhost:8000/admin/login');
    await page.fill('input[type="email"]', 'admin@eye-training.com');
    await page.fill('input[type="password"]', 'password');
    await page.click('button[type="submit"]');
    await page.waitForURL('**/admin');

    // カレンダーウィジェットが表示されるまで待つ
    await page.waitForSelector('.fc-daygrid-day-events', { timeout: 10000 });

    // 少し待つ
    await page.waitForTimeout(2000);

    console.log('Attempting manual JavaScript fix...');

    // 手動でJavaScriptを実行して改行を処理
    const result = await page.evaluate(() => {
        let processed = 0;
        document.querySelectorAll('.fc-event-title').forEach((titleEl) => {
            const text = titleEl.textContent || titleEl.innerText;
            const lines = text.split('\n');

            if (lines.length > 1) {
                let newHtml = '';
                lines.forEach((line, index) => {
                    if (index === 0) {
                        newHtml += '<div style="font-weight: bold; margin-bottom: 2px;">' + line + '</div>';
                    } else if (line.trim() !== '') {
                        newHtml += '<div style="font-size: 0.9em; line-height: 1.2;">' + line + '</div>';
                    }
                });
                titleEl.innerHTML = newHtml;
                processed++;
            }
        });
        return processed;
    });

    console.log(`Manually processed ${result} calendar events`);

    // 結果を確認
    const events = await page.locator('.fc-event').all();
    console.log(`Found ${events.length} events after processing`);

    let hasVerticalDisplay = false;

    for (const event of events) {
        const titleElement = await event.locator('.fc-event-title').first();
        const innerHTML = await titleElement.innerHTML();

        if (innerHTML.includes('<div')) {
            hasVerticalDisplay = true;
            console.log('✅ SUCCESS: Vertical display achieved with manual JavaScript');

            const divElements = await titleElement.locator('div').all();
            for (const div of divElements) {
                const divText = await div.textContent();
                console.log(`  - ${divText}`);
            }
        }
    }

    // スクリーンショットを撮る
    await page.screenshot({ path: 'calendar-manual-fix.png', fullPage: true });

    if (hasVerticalDisplay) {
        console.log('\n✅ MANUAL FIX SUCCESSFUL: Calendar is now displaying customer names vertically');
        console.log('The eventDidMount function in PHP is not working, but manual JavaScript works.');
    } else {
        console.log('\n❌ MANUAL FIX FAILED: Still not displaying vertically');
    }
});