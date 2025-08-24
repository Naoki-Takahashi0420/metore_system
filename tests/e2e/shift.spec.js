import { test, expect } from '@playwright/test';

test.describe('シフト管理テスト', () => {
    test('シフト管理ページ基本表示テスト', async ({ page }) => {
        // 管理画面にログイン
        await page.goto('/admin/login');
        await page.getByLabel('メールアドレス').fill('superadmin@xsyumeno.com');
        await page.getByLabel('パスワード').fill('password');
        await page.getByRole('button', { name: 'ログイン' }).click();
        
        // シフト管理ページに移動
        await page.getByRole('link', { name: 'シフト管理' }).click();
        
        // ページが正常に読み込まれることを確認
        await expect(page).toHaveURL(/.*\/admin\/shifts/);
        
        // 統計ウィジェットエリアが表示されることを確認
        await expect(page.locator('.fi-wi-stats-overview')).toBeVisible();
        
        // テーブルが表示されることを確認
        await expect(page.locator('table')).toBeVisible();
        
        console.log('✅ シフト管理ページの基本表示テストが正常に完了しました');
    });
});