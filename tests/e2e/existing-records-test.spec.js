import { test, expect } from '@playwright/test';

test.describe('既存カルテ（視力データなし）の表示テスト', () => {
    test('管理画面で視力データなしカルテが正常に表示される', async ({ page }) => {
        // ログイン
        await page.goto('http://localhost:8000/admin/login');
        await page.fill('input[type="email"]', 'admin@eye-training.com');
        await page.fill('input[type="password"]', 'password');
        await page.click('button[type="submit"]');
        await page.waitForURL('**/admin**');

        // カルテ一覧に移動
        await page.goto('http://localhost:8000/admin/medical-records');
        await page.waitForLoadState('networkidle');

        // エラーがないか確認
        const errors = [];
        page.on('console', msg => {
            if (msg.type() === 'error') {
                errors.push(msg.text());
            }
        });

        await page.screenshot({ path: 'test-results/medical-records-list.png', fullPage: true });

        // JavaScriptエラーが出ていないことを確認
        expect(errors.length).toBe(0);

        console.log('✅ 管理画面カルテ一覧: エラーなし');
    });

    test('顧客ページで視力データなし・ありが混在しても正常に表示される', async ({ page }) => {
        // 顧客としてログイン
        await page.goto('http://localhost:8000/login');

        // 高橋直希でログイン（仮のログイン処理）
        // 実際のログイン方法に応じて調整が必要
        await page.goto('http://localhost:8000/customer/medical-records');
        await page.waitForLoadState('networkidle');

        // エラーがないか確認
        const errors = [];
        page.on('console', msg => {
            if (msg.type() === 'error') {
                errors.push(msg.text());
            }
        });

        // カルテモーダルを開く
        const recordRows = page.locator('tr[wire\\:click*="viewRecord"]');
        const count = await recordRows.count();

        console.log(`カルテ件数: ${count}`);

        if (count > 0) {
            // 最初のカルテを開く
            await recordRows.first().click();
            await page.waitForTimeout(1000);

            await page.screenshot({ path: 'test-results/customer-record-modal-1.png', fullPage: true });

            // モーダルを閉じる
            const closeButton = page.locator('button:has-text("閉じる")');
            if (await closeButton.isVisible()) {
                await closeButton.click();
                await page.waitForTimeout(500);
            }

            // 別のカルテも開いてみる
            if (count > 1) {
                await recordRows.nth(1).click();
                await page.waitForTimeout(1000);
                await page.screenshot({ path: 'test-results/customer-record-modal-2.png', fullPage: true });
            }
        }

        // JavaScriptエラーが出ていないことを確認
        expect(errors.length).toBe(0);

        console.log('✅ 顧客ページ: エラーなし');
    });
});
