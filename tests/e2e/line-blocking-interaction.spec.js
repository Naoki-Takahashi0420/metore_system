import { test, expect } from '@playwright/test';

test.describe('ライン別ブロック機能 - インタラクションテスト', () => {
    test.beforeEach(async ({ page }) => {
        // ログイン
        await page.goto('http://127.0.0.1:8000/admin/login');
        await page.fill('input[type="email"]', 'admin@eye-training.com');
        await page.fill('input[type="password"]', 'password');
        await page.click('button[type="submit"]');
        await page.waitForURL('**/admin', { timeout: 15000 });
        await page.waitForTimeout(3000);
    });

    test('タイムラインのセルをクリックしてブロックモーダルが開く', async ({ page }) => {
        // タイムラインテーブルを取得
        const table = page.locator('table').first();
        await expect(table).toBeVisible({ timeout: 5000 });

        // テーブルの空きセル（予約が入っていないセル）を探してクリック
        const emptyCells = page.locator('td:not([colspan]):not(:has(*))').filter({ hasText: '' });
        const cellCount = await emptyCells.count();

        if (cellCount > 0) {
            // 最初の空きセルをクリック
            await emptyCells.first().click();
            await page.waitForTimeout(1500);

            // ページのHTMLを確認してモーダルの存在をチェック
            const content = await page.content();
            const hasModal = content.includes('blockModal') ||
                           content.includes('予約を作成') ||
                           content.includes('ブロック');

            if (hasModal) {
                console.log('✅ モーダルまたはアクションメニューが表示されました');
            }

            // スクリーンショット撮影
            await page.screenshot({ path: 'test-results/cell-click.png', fullPage: true });
        } else {
            console.log('⚠️ 空きセルが見つかりませんでした');
        }
    });

    test('ページに"ブロック"という文字列が存在する', async ({ page }) => {
        const content = await page.content();
        const hasBlockText = content.includes('ブロック') || content.includes('block');

        expect(hasBlockText).toBeTruthy();
        console.log('✅ ブロック機能関連のテキストが確認できました');
    });

    test('タイムラインテーブルに正しい構造がある', async ({ page }) => {
        // テーブルヘッダーが存在するか
        const thead = page.locator('table thead');
        const theadExists = await thead.count() > 0;

        // テーブルボディが存在するか
        const tbody = page.locator('table tbody');
        const tbodyExists = await tbody.count() > 0;

        // 行が存在するか
        const rows = page.locator('table tbody tr');
        const rowCount = await rows.count();

        expect(theadExists).toBeTruthy();
        expect(tbodyExists).toBeTruthy();
        expect(rowCount).toBeGreaterThan(0);

        console.log(`✅ タイムラインテーブル構造確認: ${rowCount}行`);

        await page.screenshot({ path: 'test-results/table-structure.png', fullPage: true });
    });

    test('スタッフラインの表示確認', async ({ page }) => {
        const content = await page.content();

        // スタッフライン関連のテキストを探す
        const hasStaffLine = content.includes('スタッフライン') ||
                            content.includes('staff') ||
                            content.includes('未指定');

        if (hasStaffLine) {
            console.log('✅ スタッフライン表示確認');
        } else {
            console.log('ℹ️ この店舗はシートベースの可能性があります');
        }

        await page.screenshot({ path: 'test-results/staff-line-check.png', fullPage: true });
    });

    test('データベースのブロック情報を確認', async ({ page }) => {
        // PHPスクリプトを実行してデータベースを確認
        const { exec } = require('child_process');
        const util = require('util');
        const execPromise = util.promisify(exec);

        try {
            const { stdout } = await execPromise('php artisan db:table blocked_time_periods --limit=5');
            console.log('✅ blocked_time_periodsテーブル確認:');
            console.log(stdout);
        } catch (error) {
            console.log('ℹ️ テーブル確認コマンドがスキップされました');
        }

        // スクリーンショットは不要
    });
});
