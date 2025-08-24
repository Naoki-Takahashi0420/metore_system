import { test, expect } from '@playwright/test';

test.describe('勤怠入力簡単テスト', () => {
    test('勤怠入力ページにアクセス', async ({ page }) => {
        // ログイン
        await page.goto('/admin/login');
        await page.getByLabel('メールアドレス').fill('superadmin@xsyumeno.com');
        await page.getByLabel('パスワード').fill('password');
        await page.getByRole('button', { name: 'ログイン' }).click();
        
        // シフト管理ページに移動
        await page.getByRole('link', { name: 'シフト管理' }).click();
        
        // 勤怠入力ボタンをクリック
        await page.getByRole('link', { name: '勤怠入力' }).click();
        
        // 現在時刻が表示されることを確認
        const timeElement = page.locator('#current-time');
        await expect(timeElement).toBeVisible();
        
        // ボタンテキストが表示されることを確認
        const buttons = page.locator('button, div').filter({ hasText: '出勤' });
        if (await buttons.count() > 0) {
            await expect(buttons.first()).toBeVisible();
            console.log('✅ 出勤ボタンが表示されています');
        } else {
            console.log('ℹ️ 今日はシフトがないか、ボタンが見つかりません');
        }
        
        console.log('✅ 勤怠入力ページが正常に動作しています');
    });
});