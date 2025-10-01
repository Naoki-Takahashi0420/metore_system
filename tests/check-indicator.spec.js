import { test } from '@playwright/test';

test('タイムラインインジケーター確認', async ({ page }) => {
    console.log('📍 ダッシュボードへ移動中...');
    await page.goto('http://localhost:8000/admin/login');

    console.log('🔑 ログイン中...');
    await page.locator('input[type="email"]').fill('admin@eye-training.com');
    await page.locator('input[type="password"]').fill('password');
    await page.locator('button[type="submit"]').click();

    console.log('⏳ ダッシュボード読み込み中...');
    await page.waitForURL('**/admin', { timeout: 10000 });
    await page.waitForTimeout(5000);

    console.log('📸 スクリーンショット撮影中...');
    await page.screenshot({ path: 'dashboard-full.png', fullPage: true });
    console.log('✅ スクリーンショット保存: dashboard-full.png');

    // インジケーター確認
    const indicator = page.locator('#current-time-indicator');
    const exists = await indicator.count() > 0;

    console.log('\n=== インジケーター確認 ===');
    console.log('存在:', exists ? '✅' : '❌');

    if (exists) {
        const left = await indicator.evaluate(el => el.style.left);
        const display = await indicator.evaluate(el => window.getComputedStyle(el).display);
        const startHour = await indicator.getAttribute('data-timeline-start');

        console.log('left位置:', left);
        console.log('display:', display);
        console.log('開始時刻:', startHour);
    }

    // タイムラインテーブル確認
    const table = page.locator('.timeline-table');
    const tableExists = await table.count() > 0;
    console.log('\n=== テーブル ===');
    console.log('存在:', tableExists ? '✅' : '❌');

    if (tableExists) {
        const firstRow = table.locator('tbody tr').first();
        const cells = await firstRow.locator('td').all();
        if (cells.length >= 2) {
            const cell1Width = await cells[0].evaluate(el => el.offsetWidth);
            const cell2Width = await cells[1].evaluate(el => el.offsetWidth);
            console.log('1列目幅:', cell1Width + 'px');
            console.log('2列目幅:', cell2Width + 'px');
        }
    }

    await page.close();
});
