import { test } from '@playwright/test';

test('コンソールログ確認', async ({ page }) => {
    // コンソールメッセージをキャプチャ
    page.on('console', msg => {
        const text = msg.text();
        if (text.includes('インジケーター') ||
            text.includes('位置') ||
            text.includes('createTime') ||
            text.includes('updateIndicator') ||
            text.includes('セル幅')) {
            console.log('🔍', text);
        }
    });

    await page.goto('http://localhost:8000/admin/login');
    await page.locator('input[type="email"]').fill('admin@eye-training.com');
    await page.locator('input[type="password"]').fill('password');
    await page.locator('button[type="submit"]').click();
    await page.waitForURL('**/admin', { timeout: 10000 });

    // 15秒待ってJavaScriptの実行を確認
    await page.waitForTimeout(15000);

    console.log('\n✅ テスト完了');
    await page.close();
});
