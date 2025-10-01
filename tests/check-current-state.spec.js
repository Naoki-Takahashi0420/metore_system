import { test } from '@playwright/test';

test('現在の状態確認', async ({ page }) => {
    page.on('console', msg => {
        const text = msg.text();
        if (text.includes('位置更新') || text.includes('セル幅')) {
            console.log('🔍', text);
        }
    });

    await page.goto('http://localhost:8000/admin/login');
    await page.locator('input[type="email"]').fill('admin@eye-training.com');
    await page.locator('input[type="password"]').fill('password');
    await page.locator('button[type="submit"]').click();
    await page.waitForURL('**/admin', { timeout: 10000 });
    await page.waitForTimeout(5000);

    // 現在の状態を取得
    const state = await page.evaluate(() => {
        const indicator = document.getElementById('current-time-indicator');
        const now = new Date().toLocaleString("en-US", {timeZone: "Asia/Tokyo"});
        const jstDate = new Date(now);

        return {
            indicatorExists: !!indicator,
            leftPosition: indicator?.style.left,
            computedLeft: indicator ? window.getComputedStyle(indicator).left : null,
            dataStart: indicator?.dataset.timelineStart,
            dataSlot: indicator?.dataset.slotDuration,
            currentHour: jstDate.getHours(),
            currentMinute: jstDate.getMinutes()
        };
    });

    console.log('\n=== 現在の状態 ===');
    console.log('インジケーター存在:', state.indicatorExists ? '✅' : '❌');
    console.log('style.left:', state.leftPosition);
    console.log('computed left:', state.computedLeft);
    console.log('data-timeline-start:', state.dataStart);
    console.log('data-slot-duration:', state.dataSlot);
    console.log('現在時刻:', `${state.currentHour}:${String(state.currentMinute).padStart(2, '0')}`);

    await page.screenshot({ path: 'current-state.png', fullPage: true });
    console.log('✅ スクリーンショット保存: current-state.png');

    await page.close();
});
