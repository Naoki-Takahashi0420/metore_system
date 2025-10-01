import { test } from '@playwright/test';

test('関数デバッグ', async ({ page }) => {
    // コンソールメッセージをキャプチャ
    page.on('console', msg => console.log('🖥️', msg.text()));

    await page.goto('http://localhost:8000/admin/login');
    await page.locator('input[type="email"]').fill('admin@eye-training.com');
    await page.locator('input[type="password"]').fill('password');
    await page.locator('button[type="submit"]').click();
    await page.waitForURL('**/admin', { timeout: 10000 });
    await page.waitForTimeout(5000);

    // updateIndicatorPosition()を実行してログを確認
    await page.evaluate(() => {
        if (typeof window.updateIndicatorPosition === 'function') {
            console.log('=== updateIndicatorPosition開始 ===');
            window.updateIndicatorPosition();
            console.log('=== updateIndicatorPosition終了 ===');
        } else {
            console.log('❌ window.updateIndicatorPosition is not a function');
            console.log('Type:', typeof window.updateIndicatorPosition);
        }
    });

    await page.waitForTimeout(2000);
    await page.close();
});
