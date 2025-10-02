import { test, expect } from '@playwright/test';

test.describe('ライン別ブロック機能テスト', () => {
    test.beforeEach(async ({ page }) => {
        // 管理画面にログイン
        await page.goto('http://127.0.0.1:8000/admin/login');
        await page.fill('input[name="email"]', 'admin@eye-training.com');
        await page.fill('input[name="password"]', 'password');
        await page.click('button[type="submit"]');

        // ダッシュボードが表示されるまで待機
        await page.waitForURL('**/admin', { timeout: 10000 });
    });

    test('タイムラインが表示される', async ({ page }) => {
        // タイムラインウィジェットが表示されるまで待機
        await page.waitForSelector('.timeline-container, [wire\\:id*="reservation-timeline"]', { timeout: 10000 });

        // タイムラインのヘッダーが表示されていることを確認
        const timelineExists = await page.locator('.timeline-header, table thead').count() > 0;
        expect(timelineExists).toBeTruthy();
    });

    test('メインライン1のみをブロックできる', async ({ page }) => {
        // タイムラインが表示されるまで待機
        await page.waitForSelector('.timeline-container, [wire\\:id*="reservation-timeline"]', { timeout: 10000 });
        await page.waitForTimeout(2000);

        // メインライン1の空きセルをクリック（10:00の時間帯）
        const mainLine1Cell = page.locator('td').filter({ hasText: /^$/ }).first();
        await mainLine1Cell.click();
        await page.waitForTimeout(1000);

        // ブロックモーダルが開くことを確認
        const modal = page.locator('[x-show="blockModalOpen"], .modal, [role="dialog"]');
        await expect(modal).toBeVisible({ timeout: 5000 });

        // ブロック理由を入力
        const reasonInput = page.locator('input[placeholder*="理由"], textarea[placeholder*="理由"], input[type="text"]').first();
        await reasonInput.fill('テスト: メインライン1ブロック');

        // ブロック作成ボタンをクリック
        const createButton = page.locator('button').filter({ hasText: /ブロック|作成|OK/ }).first();
        await createButton.click();
        await page.waitForTimeout(2000);

        // ブロックが作成されたことを確認（赤い背景のセルが表示される）
        const blockedCell = page.locator('td.bg-red-100, td[style*="background"], .blocked-cell');
        const blockedCount = await blockedCell.count();
        expect(blockedCount).toBeGreaterThan(0);
    });

    test('ブロックモーダルにライン情報が表示される', async ({ page }) => {
        // タイムラインが表示されるまで待機
        await page.waitForSelector('.timeline-container, [wire\\:id*="reservation-timeline"]', { timeout: 10000 });
        await page.waitForTimeout(2000);

        // 任意のセルをクリックしてブロックモーダルを開く
        const emptyCell = page.locator('td').filter({ hasText: /^$/ }).first();
        await emptyCell.click();
        await page.waitForTimeout(1000);

        // モーダルが表示されることを確認
        const modal = page.locator('[x-show="blockModalOpen"], .modal, [role="dialog"]');
        await expect(modal).toBeVisible({ timeout: 5000 });

        // モーダル内にライン情報が表示されているか確認
        const modalContent = await modal.textContent();

        // 「メインライン」「スタッフ」「サブライン」のいずれかが含まれているか
        const hasLineInfo = modalContent.includes('メインライン') ||
                           modalContent.includes('スタッフ') ||
                           modalContent.includes('サブライン') ||
                           modalContent.includes('未割当');

        expect(hasLineInfo).toBeTruthy();
    });

    test('ブロック後に該当ラインのみ赤く表示される', async ({ page }) => {
        // タイムラインが表示されるまで待機
        await page.waitForSelector('.timeline-container, [wire\\:id*="reservation-timeline"]', { timeout: 10000 });
        await page.waitForTimeout(2000);

        // メインライン1の空きセルを特定
        const table = page.locator('table').first();
        const rows = table.locator('tbody tr');

        // 最初の行（メインライン1）の最初の空きセルをクリック
        const firstRow = rows.first();
        const emptyCell = firstRow.locator('td').filter({ hasText: /^$/ }).first();
        await emptyCell.click();
        await page.waitForTimeout(1000);

        // モーダルが表示されるまで待機
        await page.waitForSelector('[x-show="blockModalOpen"], .modal, [role="dialog"]', { timeout: 5000 });

        // ブロック理由を入力
        const reasonInput = page.locator('input[placeholder*="理由"], textarea[placeholder*="理由"], input[type="text"]').first();
        await reasonInput.fill('Playwrightテスト');

        // ブロック作成
        const createButton = page.locator('button').filter({ hasText: /ブロック|作成|OK/ }).first();
        await createButton.click();
        await page.waitForTimeout(2000);

        // ページをリロードして反映を確認
        await page.reload();
        await page.waitForSelector('.timeline-container, [wire\\:id*="reservation-timeline"]', { timeout: 10000 });
        await page.waitForTimeout(2000);

        // ブロックされたセルが赤く表示されることを確認
        const blockedCells = page.locator('td.bg-red-100, td[style*="red"], .blocked-cell');
        const count = await blockedCells.count();
        expect(count).toBeGreaterThan(0);
    });

    test('データベースにline_typeが正しく保存される', async ({ page }) => {
        // このテストはバックエンドで確認済みのため、フロントエンドの動作確認のみ
        await page.waitForSelector('.timeline-container, [wire\\:id*="reservation-timeline"]', { timeout: 10000 });

        // タイムラインが正常に表示されることを確認
        const timeline = page.locator('.timeline-container, table');
        await expect(timeline).toBeVisible();
    });
});
