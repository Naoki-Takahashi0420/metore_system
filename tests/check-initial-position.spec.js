import { test } from '@playwright/test';

test('初期位置確認', async ({ page }) => {
    await page.goto('http://localhost:8000/admin/login');
    await page.locator('input[type="email"]').fill('admin@eye-training.com');
    await page.locator('input[type="password"]').fill('password');
    await page.locator('button[type="submit"]').click();
    await page.waitForURL('**/admin', { timeout: 10000 });

    // ページ読み込み直後（500ms）の位置を確認
    await page.waitForTimeout(500);

    const initialState = await page.evaluate(() => {
        const indicator = document.getElementById('current-time-indicator');
        return {
            exists: !!indicator,
            left: indicator?.style.left,
            computedLeft: indicator ? window.getComputedStyle(indicator).left : null
        };
    });

    console.log('\n=== 初期位置（500ms後） ===');
    console.log('存在:', initialState.exists ? '✅' : '❌');
    console.log('style.left:', initialState.left);
    console.log('computed left:', initialState.computedLeft);

    await page.screenshot({ path: 'initial-position.png', fullPage: true });

    // 5秒後（JavaScript更新後）の位置を確認
    await page.waitForTimeout(4500);

    const updatedState = await page.evaluate(() => {
        const indicator = document.getElementById('current-time-indicator');
        return {
            left: indicator?.style.left,
            computedLeft: indicator ? window.getComputedStyle(indicator).left : null
        };
    });

    console.log('\n=== JavaScript更新後（5秒後） ===');
    console.log('style.left:', updatedState.left);
    console.log('computed left:', updatedState.computedLeft);

    await page.close();
});
