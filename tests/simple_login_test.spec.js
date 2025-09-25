import { test, expect } from '@playwright/test';

test('Simple login and dashboard access test', async ({ page }) => {
    console.log('=== 簡単なログインテスト開始 ===');

    // ログインページへ移動
    await page.goto('http://127.0.0.1:8003/admin/login');
    console.log('ログインページに移動完了');

    // ログインフォームの確認（Filament固有のセレクター）
    const emailInput = await page.$('#data\\.email');
    const passwordInput = await page.$('input[wire\\:model="data.password"]');
    const submitButton = await page.$('button[type="submit"]:has-text("ログイン")');

    expect(emailInput).not.toBeNull();
    expect(passwordInput).not.toBeNull();
    expect(submitButton).not.toBeNull();

    // ログイン実行
    await page.fill('#data\\.email', 'admin@eye-training.com');
    await page.fill('input[wire\\:model="data.password"]', 'password');
    console.log('ログイン情報入力完了');

    await page.click('button[type="submit"]:has-text("ログイン")');
    console.log('ログインボタンクリック完了');

    // ログイン後のページ遷移を待機
    await page.waitForLoadState('networkidle');
    console.log('ページロード完了');

    // 現在のURLを確認
    const currentUrl = page.url();
    console.log('現在のURL:', currentUrl);

    // ダッシュボードまたは管理画面にいることを確認
    expect(currentUrl).toContain('/admin');

    // ページタイトルを確認
    const pageTitle = await page.title();
    console.log('ページタイトル:', pageTitle);

    console.log('✅ ログインテスト完了');
});