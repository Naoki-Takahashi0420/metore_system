import { test, expect } from '@playwright/test';

test.describe('管理画面 - 店舗フィルター確認テスト', () => {
    const baseURL = 'http://localhost:8000';
    const adminEmail = 'admin@eye-training.com';
    const adminPassword = 'password';

    test.beforeEach(async ({ page }) => {
        // 管理画面にログイン
        await page.goto(`${baseURL}/admin/login`);
        await page.waitForLoadState('networkidle');

        // Filamentのフィールドが表示されるまで待機
        await page.waitForSelector('input[type="email"], input[id*="email"]', { timeout: 15000 });

        const emailField = page.locator('input[type="email"], input[id*="email"]').first();
        await emailField.fill(adminEmail);

        const passwordField = page.locator('input[type="password"]').first();
        await passwordField.fill(adminPassword);

        await page.click('button[type="submit"]');
        await page.waitForURL(/.*\/admin(?!\/login).*/, { timeout: 15000 });
        await page.waitForLoadState('networkidle');
    });

    test('サブスクリプション一覧に店舗フィルターが表示される', async ({ page }) => {
        await page.goto(`${baseURL}/admin/subscriptions`);
        await page.waitForLoadState('networkidle');

        // フィルターボタンをクリック
        const filterButton = page.locator('button').filter({ hasText: /フィルター|Filter/ }).or(page.locator('[aria-label*="フィルター"], [aria-label*="Filter"]'));

        if (await filterButton.count() > 0) {
            await filterButton.first().click();
            await page.waitForTimeout(500);
        }

        // 店舗フィルターの存在を確認
        const storeFilterExists = await page.locator('label', { hasText: '店舗' }).count() > 0 ||
                                   await page.locator('select, [role="combobox"]').filter({ hasText: /店舗|銀座|渋谷/ }).count() > 0;

        console.log('サブスクリプション - 店舗フィルター存在:', storeFilterExists);

        if (!storeFilterExists) {
            // デバッグ: ページ内容を確認
            const pageText = await page.textContent('body');
            console.log('ページに「店舗」という文字列が含まれるか:', pageText.includes('店舗'));

            // フィルター関連の要素をすべて取得
            const filterElements = await page.locator('[class*="filter"], [class*="Filter"]').allTextContents();
            console.log('フィルター関連要素:', filterElements);
        }

        expect(storeFilterExists).toBeTruthy();
        console.log('✅ サブスクリプション一覧に店舗フィルターが存在します');
    });

    test('カルテ一覧に店舗フィルターが表示される', async ({ page }) => {
        await page.goto(`${baseURL}/admin/medical-records`);
        await page.waitForLoadState('networkidle');

        // フィルターボタンをクリック
        const filterButton = page.locator('button').filter({ hasText: /フィルター|Filter/ }).or(page.locator('[aria-label*="フィルター"], [aria-label*="Filter"]'));

        if (await filterButton.count() > 0) {
            await filterButton.first().click();
            await page.waitForTimeout(500);
        }

        // 店舗フィルターの存在を確認
        const storeFilterExists = await page.locator('label', { hasText: '店舗' }).count() > 0 ||
                                   await page.locator('select, [role="combobox"]').filter({ hasText: /店舗|銀座|渋谷/ }).count() > 0;

        console.log('カルテ - 店舗フィルター存在:', storeFilterExists);

        if (!storeFilterExists) {
            // デバッグ: ページ内容を確認
            const pageText = await page.textContent('body');
            console.log('ページに「店舗」という文字列が含まれるか:', pageText.includes('店舗'));

            // フィルター関連の要素をすべて取得
            const filterElements = await page.locator('[class*="filter"], [class*="Filter"]').allTextContents();
            console.log('フィルター関連要素:', filterElements);
        }

        expect(storeFilterExists).toBeTruthy();
        console.log('✅ カルテ一覧に店舗フィルターが存在します');
    });
});
