import { test, expect } from '@playwright/test';

test('アプリケーション基本動作確認', async ({ page }) => {
  // ホームページにアクセス
  await page.goto('/');
  
  // ページが正しく読み込まれたか確認
  await expect(page).toHaveURL('http://127.0.0.1:8000/');
  
  // 管理画面ログインページへアクセス
  await page.goto('/admin/login');
  
  // ログインフォームが表示されているか確認
  await page.waitForTimeout(2000); // 2秒待機
  
  // Filamentのログインフォームのセレクタを使用
  const emailInput = page.locator('input[type="email"]').first();
  const passwordInput = page.locator('input[type="password"]').first();
  const submitButton = page.locator('button[type="submit"]').first();
  
  // 要素が存在することを確認
  await expect(emailInput).toBeVisible();
  await expect(passwordInput).toBeVisible();
  await expect(submitButton).toBeVisible();
  
  // ログイン実行
  await emailInput.fill('admin@xsyumeno.com');
  await passwordInput.fill('password');
  await submitButton.click();
  
  // ダッシュボードへの遷移を確認（タイムアウトを延長）
  await page.waitForURL('**/admin', { timeout: 10000 });
  
  // ダッシュボードの要素を確認
  const dashboardTitle = page.locator('h1').first();
  await expect(dashboardTitle).toBeVisible();
  
  console.log('テスト成功：アプリケーションが正常に動作しています');
});