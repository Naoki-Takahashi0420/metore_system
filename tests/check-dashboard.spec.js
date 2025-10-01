import { test } from '@playwright/test';

test('ダッシュボード内容確認', async ({ page }) => {
    await page.goto('http://localhost:8000/admin/login');
    await page.locator('input[type="email"]').fill('admin@eye-training.com');
    await page.locator('input[type="password"]').fill('password');
    await page.locator('button[type="submit"]').click();
    await page.waitForURL('**/admin', { timeout: 10000 });
    await page.waitForTimeout(5000);

    // ページタイトル
    const title = await page.title();
    console.log('ページタイトル:', title);

    // ウィジェット確認
    const widgets = await page.locator('[wire\\:id]').all();
    console.log('ウィジェット数:', widgets.length);

    // タイムラインウィジェット確認
    const timelineWidget = page.locator('text=予約タイムライン');
    const hasTimeline = await timelineWidget.count() > 0;
    console.log('予約タイムライン:', hasTimeline ? '✅' : '❌');

    // H1タグ確認
    const h1 = await page.locator('h1').allTextContents();
    console.log('H1タグ:', h1);

    // body内のテキスト一部
    const bodyText = await page.locator('body').textContent();
    console.log('body (最初の500文字):', bodyText?.substring(0, 500));

    await page.screenshot({ path: 'dashboard-debug.png', fullPage: true });
    await page.close();
});
