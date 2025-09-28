import { test, expect } from '@playwright/test';

test('Debug console output for eventContent', async ({ page }) => {
    // コンソールログをすべてキャプチャ
    const consoleLogs = [];
    page.on('console', msg => {
        const text = msg.text();
        consoleLogs.push(text);
        if (text.includes('Event:') || text.includes('CustomerList:') || text.includes('No customerList')) {
            console.log('🔍 Debug:', text);
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

    // eventContentが実行されたか確認
    const eventContentLogs = consoleLogs.filter(log => log.includes('Event:') || log.includes('CustomerList:'));
    if (eventContentLogs.length === 0) {
        console.log('❌ eventContent function was NOT executed');
    } else {
        console.log(`✅ eventContent function executed ${eventContentLogs.length} times`);
    }

    // イベントの構造を確認
    const eventData = await page.evaluate(() => {
        const events = [];
        document.querySelectorAll('.fc-event').forEach(el => {
            const structure = {
                html: el.innerHTML,
                childCount: el.children.length,
                textContent: el.textContent
            };
            events.push(structure);
        });
        return events;
    });

    console.log('\n📊 Event structure:');
    eventData.forEach((event, i) => {
        console.log(`Event ${i + 1}:`);
        console.log(`  Child count: ${event.childCount}`);
        console.log(`  Text: ${event.textContent}`);
        if (event.html.includes('<div')) {
            console.log('  ✓ Contains div elements');
        }
    });
});