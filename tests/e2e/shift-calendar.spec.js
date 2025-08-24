import { test, expect } from '@playwright/test';

test.describe('シフトカレンダーテスト', () => {
    test('シフトカレンダーページ表示と操作テスト', async ({ page }) => {
        // 管理画面にログイン
        await page.goto('/admin/login');
        await page.getByLabel('メールアドレス').fill('superadmin@xsyumeno.com');
        await page.getByLabel('パスワード').fill('password');
        await page.getByRole('button', { name: 'ログイン' }).click();
        
        // シフトカレンダーページに直接アクセス
        await page.goto('/admin/shifts/calendar');
        
        // ページが正常に読み込まれることを確認
        await expect(page).toHaveURL(/.*\/admin\/shifts\/calendar/);
        
        // ページタイトルが表示されることを確認
        await expect(page.getByRole('heading', { name: 'シフトカレンダー' })).toBeVisible();
        
        // カレンダーが表示されることを確認
        await expect(page.locator('.grid-cols-7')).toBeVisible();
        
        // 曜日ヘッダーが表示されることを確認
        await expect(page.getByText('月')).toBeVisible();
        await expect(page.getByText('火')).toBeVisible();
        await expect(page.getByText('水')).toBeVisible();
        
        // ステータス凡例が表示されることを確認
        await expect(page.getByText('ステータス')).toBeVisible();
        await expect(page.getByText('予定')).toBeVisible();
        await expect(page.getByText('勤務中')).toBeVisible();
        
        console.log('✅ シフトカレンダーページの表示と操作テストが正常に完了しました');
    });
});