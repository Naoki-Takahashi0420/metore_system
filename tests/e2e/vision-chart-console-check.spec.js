import { test, expect } from '@playwright/test';

test('コンソールエラーとグラフ描画を詳細確認', async ({ page }) => {
    // コンソールメッセージをキャプチャ
    const consoleMessages = [];
    const consoleErrors = [];

    page.on('console', msg => {
        const text = msg.text();
        consoleMessages.push({ type: msg.type(), text });
        if (msg.type() === 'error') {
            consoleErrors.push(text);
        }
    });

    // ページエラーをキャプチャ
    const pageErrors = [];
    page.on('pageerror', error => {
        pageErrors.push(error.message);
    });

    // ログイン
    await page.goto('http://localhost:8000/admin/login');
    await page.fill('input[type="email"]', 'admin@eye-training.com');
    await page.fill('input[type="password"]', 'password');
    await page.click('button[type="submit"]');
    await page.waitForURL('**/admin**');

    // カルテ詳細ページに移動
    await page.goto('http://localhost:8000/admin/medical-records/5');
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(2000); // グラフ描画を待つ

    // Canvas要素のサイズを確認
    const nakedCanvas = page.locator('#nakedVisionChart');
    const canvasExists = await nakedCanvas.count() > 0;

    if (canvasExists) {
        const canvasBox = await nakedCanvas.boundingBox();
        console.log('Canvas bounding box:', canvasBox);

        // Canvasの中身を確認
        const hasContent = await page.evaluate(() => {
            const canvas = document.getElementById('nakedVisionChart');
            if (!canvas) return false;

            const ctx = canvas.getContext('2d');
            const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);

            // ピクセルデータに何か描画されているか確認
            for (let i = 0; i < imageData.data.length; i += 4) {
                const alpha = imageData.data[i + 3];
                if (alpha > 0) {
                    return true; // 何か描画されている
                }
            }
            return false; // 空白
        });

        console.log('Canvas has content:', hasContent);
    }

    // Chart.jsとデータの確認
    const chartDebug = await page.evaluate(() => {
        return {
            chartJsLoaded: typeof Chart !== 'undefined',
            chartJsVersion: typeof Chart !== 'undefined' ? Chart.version : null,
            nakedCanvasElement: !!document.getElementById('nakedVisionChart'),
            correctedCanvasElement: !!document.getElementById('correctedVisionChart'),
            visionChartData: {
                // Bladeから渡されたデータを確認
                datesLength: window.chartDates ? window.chartDates.length : 0,
            }
        };
    });

    console.log('Chart Debug Info:', JSON.stringify(chartDebug, null, 2));
    console.log('Console Messages:', consoleMessages);
    console.log('Console Errors:', consoleErrors);
    console.log('Page Errors:', pageErrors);

    // スクリーンショット
    await page.screenshot({ path: 'test-results/vision-chart-debug.png', fullPage: true });

    expect(consoleErrors.length).toBe(0);
    expect(pageErrors.length).toBe(0);
});
