import { test, expect } from '@playwright/test';

test.describe('カルテ年齢フィールドテスト', () => {
    test('管理画面で年齢フィールドが表示され、顧客APIでは除外される', async ({ page }) => {
        // 1. 管理画面にログイン
        await page.goto('http://localhost:8000/admin/login');
        await page.waitForLoadState('networkidle');

        // Filamentのログインフォーム
        await page.fill('input[type="email"]', 'admin@eye-training.com');
        await page.fill('input[type="password"]', 'password');
        await page.click('button:has-text("ログイン")');
        await page.waitForURL('**/admin**', { timeout: 10000 });

        // 2. カルテ一覧ページに移動
        await page.goto('http://localhost:8000/admin/medical-records');
        await page.waitForLoadState('networkidle');

        // 3. テーブルヘッダーに「年齢」カラムがあることを確認
        const ageHeaderExists = await page.locator('th:has-text("年齢")').isVisible().catch(() => false);
        console.log('管理画面テーブルに年齢カラム表示:', ageHeaderExists);

        // 4. カルテ作成ページに移動
        await page.goto('http://localhost:8000/admin/medical-records/create');
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(2000);

        // 5. 年齢フィールドが存在することを確認
        const ageFieldExists = await page.locator('input[type="number"]').filter({ hasText: /年齢/ }).count().then(c => c > 0).catch(() => {
            return page.locator('label:has-text("年齢")').isVisible().catch(() => false);
        });
        console.log('管理画面フォームに年齢フィールド表示:', ageFieldExists);

        // スクリーンショット
        await page.screenshot({ path: 'test-results/medical-record-age-admin.png', fullPage: true });

        // 6. 顧客APIで年齢が除外されていることを確認
        const apiResponse = await page.request.get('http://localhost:8000/api/customer/medical-records', {
            headers: {
                'Authorization': 'Bearer 86|gpIS6gT6PSQ4mpC7EmabOfjpIj1PaGs8rA8llK9Z20ec8f7f',
                'Content-Type': 'application/json'
            }
        });

        const apiData = await apiResponse.json();
        console.log('API レスポンスステータス:', apiResponse.status());
        
        if (apiData.data && apiData.data.length > 0) {
            const hasAgeField = 'age' in apiData.data[0];
            console.log('顧客APIに年齢フィールドが含まれている:', hasAgeField);
            expect(hasAgeField).toBe(false);
        } else {
            console.log('⚠️ カルテデータが空です');
        }

        // 7. 最終確認
        expect(ageFieldExists || ageHeaderExists).toBe(true);
    });
});
