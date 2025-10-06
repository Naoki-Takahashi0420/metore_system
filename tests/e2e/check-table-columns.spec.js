import { test, expect } from '@playwright/test';

test.describe('テーブルカラム確認', () => {
    const baseURL = 'http://localhost:8000';
    const adminEmail = 'admin@eye-training.com';
    const adminPassword = 'password';

    test.beforeEach(async ({ page }) => {
        await page.goto(`${baseURL}/admin/login`);
        await page.waitForLoadState('networkidle');

        const emailField = page.locator('input[type="email"], input[id*="email"]').first();
        await emailField.fill(adminEmail);

        const passwordField = page.locator('input[type="password"]').first();
        await passwordField.fill(adminPassword);

        await page.click('button[type="submit"]');
        await page.waitForURL(/.*\/admin(?!\/login).*/, { timeout: 15000 });
        await page.waitForLoadState('networkidle');
    });

    test('サブスクリプション一覧のテーブルヘッダーを確認', async ({ page }) => {
        await page.goto(`${baseURL}/admin/subscriptions`);
        await page.waitForLoadState('networkidle');

        const headers = await page.locator('th').allTextContents();
        console.log('サブスクリプション一覧のヘッダー:', headers);

        const hasStoreColumn = headers.some(h => h.includes('店舗'));
        console.log('店舗カラムがある:', hasStoreColumn);
    });

    test('カルテ一覧のテーブルヘッダーを確認', async ({ page }) => {
        await page.goto(`${baseURL}/admin/medical-records`);
        await page.waitForLoadState('networkidle');

        const headers = await page.locator('th').allTextContents();
        console.log('カルテ一覧のヘッダー:', headers);

        const hasStoreColumn = headers.some(h => h.includes('店舗'));
        console.log('店舗カラムがある:', hasStoreColumn);
    });
});
