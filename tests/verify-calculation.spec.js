import { test } from '@playwright/test';

test('計算ロジック検証', async ({ page }) => {
    // コンソールログをキャプチャ
    page.on('console', msg => {
        const text = msg.text();
        if (text.includes('セル幅') || text.includes('位置更新') || text.includes('計算式')) {
            console.log('📊', text);
        }
    });

    await page.goto('http://localhost:8000/admin/login');
    await page.locator('input[type="email"]').fill('admin@eye-training.com');
    await page.locator('input[type="password"]').fill('password');
    await page.locator('button[type="submit"]').click();
    await page.waitForURL('**/admin', { timeout: 10000 });
    await page.waitForTimeout(5000);

    // data属性と実際のセル幅を検証
    const verification = await page.evaluate(() => {
        const indicator = document.getElementById('current-time-indicator');
        const table = document.querySelector('.timeline-table');
        const firstRow = table?.querySelector('tbody tr');
        const cells = firstRow?.querySelectorAll('td');

        return {
            exists: !!indicator,
            dataTimelineStart: indicator?.dataset.timelineStart,
            dataTimelineEnd: indicator?.dataset.timelineEnd,
            dataSlotDuration: indicator?.dataset.slotDuration,
            leftPosition: indicator?.style.left,
            firstCellWidth: cells?.[0]?.offsetWidth,
            secondCellWidth: cells?.[1]?.offsetWidth,
            totalCells: cells?.length,
            currentTime: new Date().toLocaleString("en-US", {timeZone: "Asia/Tokyo"})
        };
    });

    console.log('\n=== 検証結果 ===');
    console.log('インジケーター存在:', verification.exists ? '✅' : '❌');
    console.log('data-timeline-start:', verification.dataTimelineStart);
    console.log('data-timeline-end:', verification.dataTimelineEnd);
    console.log('data-slot-duration:', verification.dataSlotDuration);
    console.log('left位置:', verification.leftPosition);
    console.log('1列目幅:', verification.firstCellWidth + 'px');
    console.log('2列目幅:', verification.secondCellWidth + 'px');
    console.log('総セル数:', verification.totalCells);
    console.log('JST現在時刻:', verification.currentTime);

    // 手動で関数を呼び出して計算過程を確認
    await page.evaluate(() => {
        if (window.updateIndicatorPosition) {
            window.updateIndicatorPosition();
        }
    });

    await page.waitForTimeout(2000);
    await page.close();
});
