import { test } from '@playwright/test';

test('HTML確認', async ({ page }) => {
    await page.goto('http://localhost:8000/admin/login');
    await page.locator('input[type="email"]').fill('admin@eye-training.com');
    await page.locator('input[type="password"]').fill('password');
    await page.locator('button[type="submit"]').click();
    await page.waitForURL('**/admin', { timeout: 10000 });
    await page.waitForTimeout(5000);

    // インジケーター要素の確認
    const indicator = page.locator('#current-time-indicator');
    const exists = await indicator.count() > 0;

    console.log('インジケーター存在:', exists ? '✅' : '❌');

    if (exists) {
        const left = await indicator.evaluate(el => el.style.left);
        const dataStart = await indicator.getAttribute('data-timeline-start');
        const dataSlot = await indicator.getAttribute('data-slot-duration');

        console.log('left:', left);
        console.log('data-timeline-start:', dataStart);
        console.log('data-slot-duration:', dataSlot);

        // ブラウザコンソールでupdateIndicatorPosition()を手動実行
        await page.evaluate(() => {
            if (window.updateIndicatorPosition) {
                console.log('✅ updateIndicatorPosition関数が存在します');
                window.updateIndicatorPosition();
            } else {
                console.log('❌ updateIndicatorPosition関数が存在しません');
            }
        });

        await page.waitForTimeout(1000);

        const leftAfter = await indicator.evaluate(el => el.style.left);
        console.log('left (after manual call):', leftAfter);
    }

    await page.close();
});
