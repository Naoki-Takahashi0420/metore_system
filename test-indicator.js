const { test, expect } = require('@playwright/test');

test('Check timeline indicator console logs', async ({ page }) => {
    // コンソールログを記録
    const consoleLogs = [];
    page.on('console', msg => {
        consoleLogs.push(msg.text());
    });

    // ページにアクセス
    await page.goto('http://localhost:8000/admin/login');
    await page.fill('input[type="email"]', 'admin@eye-training.com');
    await page.fill('input[type="password"]', 'password');
    await page.click('button[type="submit"]');
    await page.waitForURL('**/admin');

    // タイムラインが読み込まれるまで待つ
    await page.waitForSelector('.timeline-table', { timeout: 10000 });

    // 5秒待ってから手動でインジケーター作成を試行
    await page.waitForTimeout(5000);

    await page.evaluate(() => {
        if (typeof createTimeIndicator === 'function') {
            console.log('手動でcreateTimeIndicator実行');
            createTimeIndicator();
        } else {
            console.log('createTimeIndicator関数が見つかりません');
        }
    });

    await page.waitForTimeout(2000);

    console.log('=== コンソールログ ===');
    consoleLogs.forEach(log => console.log(log));

    // インジケーターの状態を確認
    const indicatorInfo = await page.evaluate(() => {
        const indicator = document.getElementById('current-time-indicator');
        if (indicator) {
            return {
                exists: true,
                left: indicator.style.left,
                computed: window.getComputedStyle(indicator).left
            };
        }
        return { exists: false };
    });

    console.log('インジケーター状態:', indicatorInfo);
});