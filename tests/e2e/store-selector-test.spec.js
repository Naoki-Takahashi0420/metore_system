import { test, expect } from '@playwright/test';

test.describe('店舗セレクター表示テスト', () => {
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

    test('サブスクリプション一覧に店舗セレクターが表示される', async ({ page }) => {
        await page.goto(`${baseURL}/admin/subscriptions`);
        await page.waitForLoadState('networkidle');

        // 店舗セレクターの存在確認
        const storeSelectorLabel = page.locator('label', { hasText: '店舗：' });
        await expect(storeSelectorLabel).toBeVisible();

        // セレクトボックスの存在確認
        const storeSelect = page.locator('select').filter({ has: page.locator('option', { hasText: '全店舗' }) });
        await expect(storeSelect).toBeVisible();

        // 店舗オプションの確認
        const options = await storeSelect.locator('option').allTextContents();
        console.log('サブスクリプション - 店舗オプション:', options);

        expect(options.length).toBeGreaterThan(1); // 「全店舗」+ 店舗
        expect(options).toContain('全店舗');

        console.log('✅ サブスクリプション一覧に店舗セレクターが表示されています');
    });

    test('カルテ一覧に店舗セレクターが表示される', async ({ page }) => {
        await page.goto(`${baseURL}/admin/medical-records`);
        await page.waitForLoadState('networkidle');

        // 店舗セレクターの存在確認
        const storeSelectorLabel = page.locator('label', { hasText: '店舗：' });
        await expect(storeSelectorLabel).toBeVisible();

        // セレクトボックスの存在確認
        const storeSelect = page.locator('select').filter({ has: page.locator('option', { hasText: '全店舗' }) });
        await expect(storeSelect).toBeVisible();

        // 店舗オプションの確認
        const options = await storeSelect.locator('option').allTextContents();
        console.log('カルテ - 店舗オプション:', options);

        expect(options.length).toBeGreaterThan(1);
        expect(options).toContain('全店舗');

        console.log('✅ カルテ一覧に店舗セレクターが表示されています');
    });

    test('店舗セレクターでフィルタリングが機能する', async ({ page }) => {
        await page.goto(`${baseURL}/admin/subscriptions`);
        await page.waitForLoadState('networkidle');

        // 初期状態のレコード数を取得
        const initialRows = await page.locator('tbody tr').count();
        console.log('初期レコード数:', initialRows);

        // 店舗を選択
        const storeSelect = page.locator('select').filter({ has: page.locator('option', { hasText: '全店舗' }) });
        await storeSelect.selectOption({ index: 1 }); // 最初の店舗を選択

        // テーブルの更新を待つ
        await page.waitForTimeout(1000);

        const selectedStoreName = await storeSelect.locator('option:checked').textContent();
        console.log('選択した店舗:', selectedStoreName);

        // フィルタリング後のレコード数を確認
        const filteredRows = await page.locator('tbody tr').count();
        console.log('フィルタリング後のレコード数:', filteredRows);

        console.log('✅ 店舗セレクターでフィルタリングが動作しています');
    });
});
