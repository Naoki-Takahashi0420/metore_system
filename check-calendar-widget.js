import { chromium } from '@playwright/test';

(async () => {
    const browser = await chromium.launch({ headless: false });
    const page = await browser.newPage();

    try {
        // ログイン
        await page.goto('http://localhost:8000/admin/login');
        await page.fill('input[type="email"]', 'admin@eye-training.com');
        await page.fill('input[type="password"]', 'password');
        await page.click('button[type="submit"]');

        // ダッシュボードに移動
        await page.waitForURL('**/admin', { timeout: 10000 });
        console.log('✅ ダッシュボードに移動');

        // ウィジェットを探す
        await page.waitForTimeout(3000);

        // カレンダーウィジェットを探す
        const calendarWidget = await page.$('.fc-daygrid');
        if (calendarWidget) {
            console.log('✅ カレンダーウィジェットが見つかりました');

            // カレンダー内のイベント（予約件数）を確認
            const events = await page.$$('.fc-event');
            console.log('カレンダー内のイベント数:', events.length);

            if (events.length > 0) {
                // 最初のイベントのテキストを取得
                const firstEventText = await events[0].textContent();
                console.log('最初のイベントのテキスト:', firstEventText);
            }
        } else {
            console.log('❌ カレンダーウィジェットが見つかりません');
        }

        // スクリーンショットを保存
        await page.screenshot({ path: 'calendar-widget-check.png', fullPage: true });
        console.log('📸 スクリーンショット保存: calendar-widget-check.png');

        // 全てのウィジェットのタイトルを取得
        const widgets = await page.$$('[data-sortable-widget]');
        console.log('\n見つかったウィジェット数:', widgets.length);

        for (let i = 0; i < widgets.length; i++) {
            const titleElement = await widgets[i].$('h2');
            if (titleElement) {
                const title = await titleElement.textContent();
                console.log(`ウィジェット ${i+1}: ${title}`);
            }
        }

    } catch (error) {
        console.error('エラー:', error.message);
    }

    await page.waitForTimeout(5000);
    await browser.close();
})();