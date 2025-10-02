import { test, expect } from '@playwright/test';

test.describe('視力推移グラフ表示テスト', () => {
    test('管理画面でグラフが表示される', async ({ page }) => {
        // ログイン
        await page.goto('http://localhost:8000/admin/login');
        await page.fill('input[type="email"]', 'admin@eye-training.com');
        await page.fill('input[type="password"]', 'password');
        await page.click('button[type="submit"]');

        // ログイン完了を待つ
        await page.waitForURL('**/admin**');

        // カルテ詳細ページに移動
        await page.goto('http://localhost:8000/admin/medical-records/5');

        // ページが読み込まれるまで待つ
        await page.waitForLoadState('networkidle');

        // スクリーンショット
        await page.screenshot({ path: 'test-results/vision-chart-admin-page.png', fullPage: true });

        // 視力推移グラフセクションが表示されているか確認
        const chartSection = page.locator('text=視力推移グラフ');
        await expect(chartSection).toBeVisible({ timeout: 10000 });

        // Canvasが存在するか確認（IDは動的生成されるので部分一致で検索）
        const nakedCanvas = page.locator('canvas[id^="nakedVisionChart"]');
        const correctedCanvas = page.locator('canvas[id^="correctedVisionChart"]');

        // どちらか一方は表示されているはず
        const nakedVisible = await nakedCanvas.isVisible().catch(() => false);
        const correctedVisible = await correctedCanvas.isVisible().catch(() => false);

        console.log('裸眼グラフ表示:', nakedVisible);
        console.log('矯正グラフ表示:', correctedVisible);

        expect(nakedVisible || correctedVisible).toBeTruthy();

        // Chart.jsが読み込まれているか確認
        const chartJsLoaded = await page.evaluate(() => {
            return typeof window.Chart !== 'undefined';
        });

        console.log('Chart.js読み込み:', chartJsLoaded);

        // 最終スクリーンショット
        await page.screenshot({ path: 'test-results/vision-chart-admin-final.png', fullPage: true });
    });

    test('Bladeテンプレートの内容を確認', async ({ page }) => {
        await page.goto('http://localhost:8000/admin/login');
        await page.fill('input[type="email"]', 'admin@eye-training.com');
        await page.fill('input[type="password"]', 'password');
        await page.click('button[type="submit"]');
        await page.waitForURL('**/admin**');

        await page.goto('http://localhost:8000/admin/medical-records/5');
        await page.waitForLoadState('networkidle');

        // HTMLソースを取得
        const htmlContent = await page.content();

        // vision_recordsデータが含まれているか
        const hasVisionData = htmlContent.includes('vision') || htmlContent.includes('chart');
        console.log('HTMLにvision関連の文字列が含まれる:', hasVisionData);

        // Chart.jsのスクリプトタグが存在するか
        const hasChartJs = htmlContent.includes('chart.umd.min.js') || htmlContent.includes('Chart');
        console.log('Chart.jsのスクリプトタグ:', hasChartJs);

        // Canvas要素が存在するか
        const hasCanvas = htmlContent.includes('nakedVisionChart') || htmlContent.includes('correctedVisionChart');
        console.log('Canvas要素:', hasCanvas);

        // コンソールエラーを確認
        page.on('console', msg => {
            if (msg.type() === 'error') {
                console.error('ブラウザコンソールエラー:', msg.text());
            }
        });

        await page.screenshot({ path: 'test-results/vision-chart-html-check.png', fullPage: true });
    });
});
