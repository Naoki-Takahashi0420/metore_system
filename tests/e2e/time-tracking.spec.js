import { test, expect } from '@playwright/test';

test.describe('勤怠入力テスト', () => {
    test('勤怠入力ページ表示テスト', async ({ page }) => {
        // 管理画面にログイン
        await page.goto('/admin/login');
        await page.getByLabel('メールアドレス').fill('superadmin@xsyumeno.com');
        await page.getByLabel('パスワード').fill('password');
        await page.getByRole('button', { name: 'ログイン' }).click();
        
        // 勤怠入力ページに直接アクセス
        await page.goto('/admin/shifts/time-tracking');
        
        // ページが正常に読み込まれることを確認
        await expect(page).toHaveURL(/.*\/admin\/shifts\/time-tracking/);
        
        // ページタイトルが表示されることを確認
        await expect(page.getByRole('heading', { name: '勤怠入力' })).toBeVisible();
        
        // 今日のシフト表示を確認
        await expect(page.getByText('今日のシフト')).toBeVisible();
        
        // 大きなボタンがある場合は確認
        const buttons = page.locator('button[wire\\:click*="clockIn"], button[wire\\:click*="startBreak"], button[wire\\:click*="endBreak"], button[wire\\:click*="clockOut"]');
        if (await buttons.count() > 0) {
            // ボタンが大きいことを確認（高さ20 = h-20）
            await expect(buttons.first()).toHaveClass(/h-20/);
        }
        
        console.log('✅ 勤怠入力ページの表示テストが正常に完了しました');
    });
});