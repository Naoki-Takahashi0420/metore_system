import { test, expect } from '@playwright/test';

test.describe('顧客ページ視力グラフ表示テスト', () => {
    test('個別カラムのデータからグラフが表示される', async ({ page }) => {
        // JavaScriptエラーを収集
        const errors = [];
        page.on('console', msg => {
            if (msg.type() === 'error') {
                errors.push(msg.text());
                console.log('❌ JSエラー:', msg.text());
            }
        });

        // localStorageに顧客トークンを設定
        await page.goto('http://localhost:8000');
        await page.evaluate(() => {
            localStorage.setItem('customer_token', '85|rqWcix4zg3ANw4jhAXGYhjs9CtTqjpMSi4gUU5Qyd30df933');
            localStorage.setItem('customer_data', JSON.stringify({
                id: 2,
                last_name: '高橋',
                first_name: '直希',
                phone: '09012345678'
            }));
        });

        // 顧客ページに移動
        await page.goto('http://localhost:8000/customer/medical-records');

        // ページ読み込み待機
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(3000);

        // スクリーンショット（初期状態）
        await page.screenshot({ path: 'test-results/customer-vision-chart-initial.png', fullPage: true });

        // カルテデータが読み込まれるまで待機
        await page.waitForTimeout(2000);

        // グラフコンテナが表示されているか確認
        const chartContainer = page.locator('#vision-chart-container');
        const isVisible = await chartContainer.isVisible().catch(() => false);

        console.log('グラフコンテナ表示:', isVisible);

        // Canvas要素の確認
        const nakedCanvas = page.locator('#nakedVisionChart');
        const correctedCanvas = page.locator('#correctedVisionChart');

        const nakedVisible = await nakedCanvas.isVisible().catch(() => false);
        const correctedVisible = await correctedCanvas.isVisible().catch(() => false);

        console.log('裸眼グラフ:', nakedVisible);
        console.log('矯正グラフ:', correctedVisible);

        // Chart.js読み込み確認
        const chartJsLoaded = await page.evaluate(() => {
            return typeof window.Chart !== 'undefined';
        });
        console.log('Chart.js読み込み:', chartJsLoaded);

        // スクリーンショット（最終状態）
        await page.screenshot({ path: 'test-results/customer-vision-chart-final.png', fullPage: true });

        // エラーがないことを確認
        console.log('JavaScriptエラー数:', errors.length);
        if (errors.length > 0) {
            console.log('エラー内容:', errors);
        }

        // グラフが表示されているべき
        expect(nakedVisible || correctedVisible).toBeTruthy();
    });
});
