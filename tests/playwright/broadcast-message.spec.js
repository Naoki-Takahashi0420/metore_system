// @ts-check
import { test, expect } from '@playwright/test';

test.describe('一斉送信機能', () => {
    test.beforeEach(async ({ page }) => {
        // 管理画面にログイン
        await page.goto('http://localhost:8000/admin/login');
        await page.waitForLoadState('networkidle');

        // Filamentのログインフォーム（super_adminロールを持つユーザーを使用）
        await page.locator('#data\\.email').fill('naoki@yumeno-marketing.jp');
        await page.locator('#data\\.password').fill('Takahashi5000');
        await page.getByRole('button', { name: 'ログイン' }).click();
        await page.waitForURL('**/admin/**', { timeout: 30000 });
        await page.waitForLoadState('networkidle');
    });

    test('店舗詳細ページにRelationManagerセクションが存在する', async ({ page }) => {
        // サイドバーから店舗管理リンクをクリック
        await page.getByRole('link', { name: '店舗管理' }).click();
        await page.waitForLoadState('networkidle');

        // 店舗一覧が表示されるまで待機
        await expect(page.locator('table')).toBeVisible({ timeout: 15000 });

        // 最初の店舗をクリック（View）
        await page.locator('table tbody tr:first-child').getByRole('link').first().click();
        await page.waitForLoadState('networkidle');

        // ページの内容を確認
        await page.waitForTimeout(2000);

        // ページを下までスクロール
        await page.evaluate(() => window.scrollTo(0, document.body.scrollHeight));
        await page.waitForTimeout(1000);

        // RelationManagerのタブまたはコンテナを探す（Filament 3形式）
        const relationManagerExists = await page.locator('[wire\\:key*="relation-manager"]').count();

        // もしくはタブリストで一斉送信を探す
        const broadcastTab = page.locator('button', { hasText: '一斉送信' });
        const tabExists = await broadcastTab.count();

        // デバッグ: ページの状態をログ
        console.log(`RelationManager containers found: ${relationManagerExists}`);
        console.log(`Broadcast tabs found: ${tabExists}`);

        // どちらかが存在すればOK
        expect(relationManagerExists + tabExists).toBeGreaterThan(0);
    });

    test('一斉送信機能の基本動作確認', async ({ page }) => {
        // サイドバーから店舗管理リンクをクリック
        await page.getByRole('link', { name: '店舗管理' }).click();
        await page.waitForLoadState('networkidle');

        // 店舗一覧が表示されるまで待機
        await expect(page.locator('table')).toBeVisible({ timeout: 15000 });

        // 最初の店舗をクリック（View）
        await page.locator('table tbody tr:first-child').getByRole('link').first().click();
        await page.waitForLoadState('networkidle');

        // ページを下までスクロール
        await page.evaluate(() => window.scrollTo(0, document.body.scrollHeight));
        await page.waitForTimeout(2000);

        // 一斉送信の見出しとボタンが見つかる
        const broadcastHeading = page.locator('h3:has-text("一斉送信")');
        await expect(broadcastHeading).toBeVisible({ timeout: 10000 });

        // 新規一斉送信ボタンが表示されるか確認
        const createButton = page.locator('button', { hasText: '新規一斉送信' });
        await expect(createButton).toBeVisible({ timeout: 10000 });
    });

    test('新規一斉送信モーダルが開く', async ({ page }) => {
        // サイドバーから店舗管理リンクをクリック
        await page.getByRole('link', { name: '店舗管理' }).click();
        await page.waitForLoadState('networkidle');

        // 店舗一覧が表示されるまで待機
        await expect(page.locator('table')).toBeVisible({ timeout: 15000 });

        // 最初の店舗をクリック（View）
        await page.locator('table tbody tr:first-child').getByRole('link').first().click();
        await page.waitForLoadState('networkidle');

        // ページを下までスクロール
        await page.evaluate(() => window.scrollTo(0, document.body.scrollHeight));
        await page.waitForTimeout(2000);

        // 新規一斉送信ボタンをクリック（Livewireイベントを待つ）
        const createButton = page.locator('button', { hasText: '新規一斉送信' });
        await expect(createButton).toBeVisible({ timeout: 15000 });

        // ボタンにスクロールしてからクリック
        await createButton.scrollIntoViewIfNeeded();
        await page.waitForTimeout(500);
        await createButton.click();

        // Livewire処理完了を待機
        await page.waitForTimeout(3000);

        // フォーム要素が見えるようになるのを待つ（モーダル内の件名入力）
        const subjectLabel = page.locator('label:has-text("件名")');
        await expect(subjectLabel).toBeVisible({ timeout: 15000 });

        // メッセージ本文ラベルも確認
        const messageLabel = page.locator('label:has-text("メッセージ本文")');
        await expect(messageLabel).toBeVisible({ timeout: 5000 });

        // 送信タイミングのラジオボタンを確認
        const timingLabel = page.locator('label:has-text("送信タイミング")');
        await expect(timingLabel).toBeVisible({ timeout: 5000 });
    });
});
