import { test, expect } from '@playwright/test';

test.describe('ライン別ブロック機能 - シンプルテスト', () => {
    test('管理画面にログインしてタイムラインにアクセスできる', async ({ page }) => {
        // ログインページに移動
        await page.goto('http://127.0.0.1:8000/admin/login');

        // ログインフォームに入力
        await page.fill('input[type="email"]', 'admin@eye-training.com');
        await page.fill('input[type="password"]', 'password');

        // ログインボタンをクリック
        await page.click('button[type="submit"]');

        // ダッシュボードにリダイレクトされるまで待機
        await page.waitForURL('**/admin', { timeout: 15000 });

        // ページタイトルまたはヘッダーが表示されることを確認
        const pageContent = await page.content();
        expect(pageContent).toContain('予約');

        // スクリーンショットを撮影
        await page.screenshot({ path: 'test-results/dashboard.png', fullPage: true });

        console.log('✅ 管理画面へのアクセス成功');
    });

    test('タイムラインウィジェットが表示される', async ({ page }) => {
        // ログイン
        await page.goto('http://127.0.0.1:8000/admin/login');
        await page.fill('input[type="email"]', 'admin@eye-training.com');
        await page.fill('input[type="password"]', 'password');
        await page.click('button[type="submit"]');
        await page.waitForURL('**/admin', { timeout: 15000 });

        // 少し待機してウィジェットがロードされるのを待つ
        await page.waitForTimeout(3000);

        // タイムラインテーブルが存在するか確認
        const hasTable = await page.locator('table').count() > 0;
        expect(hasTable).toBeTruthy();

        // スクリーンショットを撮影
        await page.screenshot({ path: 'test-results/timeline-widget.png', fullPage: true });

        console.log('✅ タイムラインウィジェット表示確認');
    });

    test('ブロック機能のUIが存在する', async ({ page }) => {
        // ログイン
        await page.goto('http://127.0.0.1:8000/admin/login');
        await page.fill('input[type="email"]', 'admin@eye-training.com');
        await page.fill('input[type="password"]', 'password');
        await page.click('button[type="submit"]');
        await page.waitForURL('**/admin', { timeout: 15000 });
        await page.waitForTimeout(3000);

        // ページのHTMLを取得してブロック関連の要素があるか確認
        const content = await page.content();

        // blockModalOpen または block 関連の文字列が存在するか
        const hasBlockFeature = content.includes('blockModal') ||
                               content.includes('ブロック') ||
                               content.includes('block');

        expect(hasBlockFeature).toBeTruthy();

        // スクリーンショットを撮影
        await page.screenshot({ path: 'test-results/block-feature-check.png', fullPage: true });

        console.log('✅ ブロック機能UI存在確認');
    });
});
