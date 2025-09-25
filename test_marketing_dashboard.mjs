import { chromium } from 'playwright';

(async () => {
    const browser = await chromium.launch({
        headless: false,
        slowMo: 500
    });
    const context = await browser.newContext();
    const page = await context.newPage();

    try {
        // ログイン画面へ
        console.log('1. ログイン画面へアクセス...');
        await page.goto('http://localhost:8000/admin/login');
        await page.waitForLoadState('networkidle');

        // スクリーンショット
        await page.screenshot({ path: 'login_page.png' });

        // ログイン
        console.log('2. ログイン処理...');
        await page.fill('input[name="email"]', 'admin@eye-training.com');
        await page.fill('input[name="password"]', 'password');
        await page.click('button[type="submit"]');

        // ダッシュボードが読み込まれるまで待機
        await page.waitForLoadState('networkidle');
        console.log('3. ログイン成功');

        // マーケティング分析ページへ直接アクセス
        console.log('4. マーケティング分析ページへアクセス...');
        await page.goto('http://localhost:8000/admin/marketing-dashboard');
        await page.waitForLoadState('networkidle');

        // エラーメッセージを確認
        const pageContent = await page.content();
        console.log('5. ページの状態を確認...');

        // エラーが表示されているか確認
        if (pageContent.includes('ComponentNotFoundException') || pageContent.includes('Internal Server Error')) {
            console.log('❌ エラーが発生しています');
            await page.screenshot({ path: 'error_page.png', fullPage: true });

            // コンソールエラーを取得
            page.on('console', msg => console.log('Console:', msg.text()));
            page.on('pageerror', error => console.log('Page error:', error.message));
        } else {
            console.log('✅ ページが正常に表示されています');
            await page.screenshot({ path: 'marketing_dashboard.png', fullPage: true });
        }

        // ページタイトルを確認
        const title = await page.title();
        console.log('ページタイトル:', title);

        // 要素の存在確認
        const hasError = await page.locator('.exception-message').count() > 0;
        if (hasError) {
            const errorMessage = await page.locator('.exception-message').textContent();
            console.log('エラーメッセージ:', errorMessage);
        }

        // 10秒間ブラウザを開いたままにする
        console.log('ブラウザを10秒間開いたままにします...');
        await page.waitForTimeout(10000);

    } catch (error) {
        console.error('エラーが発生しました:', error);
        await page.screenshot({ path: 'error_screenshot.png', fullPage: true });
    } finally {
        await browser.close();
        console.log('テスト完了');
    }
})();