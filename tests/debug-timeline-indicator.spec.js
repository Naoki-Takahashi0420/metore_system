import { test, expect } from '@playwright/test';

test('タイムラインインジケーター位置確認', async ({ page }) => {
    // 認証済み状態でダッシュボードにアクセス
    await page.goto('http://localhost:8000/admin');

    // ログインページにリダイレクトされる場合はログイン
    const url = page.url();
    if (url.includes('/login')) {
        console.log('ログインが必要です。ログイン処理を実行...');
        await page.fill('input[name="email"]', 'admin@eye-training.com');
        await page.fill('input[name="password"]', 'password');
        await page.click('button[type="submit"]');
        await page.waitForURL('**/admin', { timeout: 10000 });
    }

    await page.waitForTimeout(3000);

    // スクリーンショット撮影
    await page.screenshot({ path: 'timeline-debug-dashboard.png', fullPage: true });

    // インジケーター要素を取得
    const indicator = await page.locator('#current-time-indicator');
    const exists = await indicator.count() > 0;

    console.log('=== 🔍 インジケーター要素の存在確認 ===');
    console.log('Indicator exists:', exists);

    if (exists) {
        // data属性を取得
        const startHour = await indicator.getAttribute('data-timeline-start');
        const endHour = await indicator.getAttribute('data-timeline-end');
        const slotDuration = await indicator.getAttribute('data-slot-duration');
        const leftPosition = await indicator.evaluate(el => el.style.left);
        const classList = await indicator.evaluate(el => el.className);

        console.log('\n=== 📊 インジケーターの属性情報 ===');
        console.log('data-timeline-start:', startHour);
        console.log('data-timeline-end:', endHour);
        console.log('data-slot-duration:', slotDuration);
        console.log('left position:', leftPosition);
        console.log('class:', classList);

        // 現在時刻テキストを取得
        const timeText = await page.locator('.current-time-text').textContent();
        console.log('表示時刻:', timeText);
    } else {
        console.log('❌ インジケーター要素が見つかりません');
    }

    // タイムラインテーブルを確認
    const table = await page.locator('.timeline-table');
    const tableExists = await table.count() > 0;
    console.log('\n=== 📋 タイムラインテーブル ===');
    console.log('Table exists:', tableExists);

    if (tableExists) {
        // テーブルのヘッダー（時間）を取得
        const headers = await page.locator('.timeline-table thead th').allTextContents();
        console.log('Table headers:', headers.slice(0, 10));
    }

    // ページのコンソールログを取得
    console.log('\n=== 💬 ブラウザコンソールログ ===');
    page.on('console', msg => {
        if (msg.text().includes('🐘') || msg.text().includes('JST')) {
            console.log('Browser:', msg.text());
        }
    });

    // 再度待機してログを取得
    await page.waitForTimeout(2000);

    // 現在のJST時刻を取得
    const currentTime = await page.evaluate(() => {
        const now = new Date().toLocaleString("en-US", {timeZone: "Asia/Tokyo"});
        const jstDate = new Date(now);
        return {
            hour: jstDate.getHours(),
            minute: jstDate.getMinutes(),
            formatted: `${jstDate.getHours()}:${String(jstDate.getMinutes()).padStart(2, '0')}`
        };
    });
    console.log('\n=== ⏰ 現在時刻（JST） ===');
    console.log('現在時刻:', currentTime.formatted);

    // 計算検証
    if (exists) {
        const startHour = await indicator.getAttribute('data-timeline-start');
        const slotDuration = await indicator.getAttribute('data-slot-duration');

        const minutesFromStart = (currentTime.hour - parseInt(startHour)) * 60 + currentTime.minute;
        const cellIndex = Math.floor(minutesFromStart / parseInt(slotDuration));
        const percentageIntoCell = (minutesFromStart % parseInt(slotDuration)) / parseInt(slotDuration);
        const firstCellWidth = 36;
        const cellWidth = 48;
        const expectedLeft = firstCellWidth + (cellIndex * cellWidth) + (percentageIntoCell * cellWidth);

        console.log('\n=== 🧮 位置計算の検証 ===');
        console.log('開始時刻:', startHour + ':00');
        console.log('現在時刻:', currentTime.formatted);
        console.log('開始からの分数:', minutesFromStart);
        console.log('セルインデックス:', cellIndex);
        console.log('セル内の割合:', (percentageIntoCell * 100).toFixed(1) + '%');
        console.log('期待される位置:', expectedLeft + 'px');

        const actualLeft = await indicator.evaluate(el => el.style.left);
        console.log('実際の位置:', actualLeft);
        console.log('一致:', actualLeft === expectedLeft + 'px' ? '✅' : '❌');
    }
});
