import { test, expect } from '@playwright/test';

test.describe('Admin Panel Access Test', () => {
  const BASE_URL = 'http://127.0.0.1:8000';
  
  test('Should access admin login page', async ({ page }) => {
    await page.goto(`${BASE_URL}/admin/login`);
    
    // ログインページが表示されることを確認
    await expect(page).toHaveTitle(/Login/);
    await expect(page.locator('input[name="email"]')).toBeVisible();
    await expect(page.locator('input[name="password"]')).toBeVisible();
  });
  
  test('Should login as superadmin and access users page', async ({ page }) => {
    // ログインページにアクセス
    await page.goto(`${BASE_URL}/admin/login`);
    
    // ログイン情報を入力
    await page.fill('input[name="email"]', 'superadmin@eye-training.com');
    await page.fill('input[name="password"]', 'password');
    await page.click('button[type="submit"]');
    
    // ダッシュボードにリダイレクトされることを確認
    await expect(page).toHaveURL(/\/admin/);
    
    // ユーザー管理ページにアクセス
    await page.goto(`${BASE_URL}/admin/users`);
    
    // ユーザー一覧が表示されることを確認
    await expect(page.locator('table')).toBeVisible();
    
    console.log('✅ Admin panel access successful');
  });
  
  test('Should create new user with role selection', async ({ page }) => {
    // ログイン
    await page.goto(`${BASE_URL}/admin/login`);
    await page.fill('input[name="email"]', 'superadmin@eye-training.com');
    await page.fill('input[name="password"]', 'password');
    await page.click('button[type="submit"]');
    
    // ユーザー管理ページへ
    await page.goto(`${BASE_URL}/admin/users`);
    
    // 新規作成ボタンをクリック
    await page.click('text=新規作成');
    
    // フォームが表示されることを確認
    await expect(page.locator('input[name="name"]')).toBeVisible();
    await expect(page.locator('input[name="email"]')).toBeVisible();
    
    // ロール選択フィールドを確認
    await expect(page.locator('select')).toBeVisible();
    
    console.log('✅ User creation form accessible');
  });
});