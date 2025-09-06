import { test, expect } from '@playwright/test';

test.describe('Admin Panel Access Test', () => {
  const BASE_URL = 'http://127.0.0.1:8000';
  
  test('Should access admin login page', async ({ page }) => {
    await page.goto(`${BASE_URL}/admin/login`);
    
    // ログインページが表示されることを確認
    await expect(page).toHaveTitle(/ログイン|Login|目のトレーニング/);
    await expect(page.locator('input#data\.email')).toBeVisible();
    await expect(page.locator('input#data\.password')).toBeVisible();
  });
  
  test('Should login as superadmin and access users page', async ({ page }) => {
    test.setTimeout(60000); // 60秒のタイムアウトに設定
    
    // ログインページにアクセス
    await page.goto(`${BASE_URL}/admin/login`, {
      waitUntil: 'networkidle',
      timeout: 30000
    });
    
    // ログイン情報を入力
    await page.fill('input#data\.email', 'admin@eye-training.com');
    await page.fill('input#data\.password', 'password');
    await page.click('button[type="submit"]');
    
    // ダッシュボードにリダイレクトされることを確認
    await page.waitForURL(/\/admin/, { timeout: 30000 });
    
    // ユーザー管理ページにアクセス
    await page.goto(`${BASE_URL}/admin/users`, {
      waitUntil: 'networkidle',
      timeout: 30000
    });
    
    // ユーザー一覧が表示されることを確認
    await expect(page.locator('table')).toBeVisible({ timeout: 10000 });
    
    console.log('✅ Admin panel access successful');
  });
  
  test('Should create new user with role selection', async ({ page }) => {
    test.setTimeout(60000); // 60秒のタイムアウトに設定
    
    // ログイン
    await page.goto(`${BASE_URL}/admin/login`, {
      waitUntil: 'networkidle',
      timeout: 30000
    });
    await page.fill('input#data\.email', 'admin@eye-training.com');
    await page.fill('input#data\.password', 'password');
    await page.click('button[type="submit"]');
    
    // ダッシュボードへのリダイレクトを待つ
    await page.waitForURL(/\/admin/, { timeout: 30000 });
    
    // ユーザー管理ページへ
    await page.goto(`${BASE_URL}/admin/users`, {
      waitUntil: 'networkidle',
      timeout: 30000
    });
    
    // 新規作成ボタンをクリック
    await page.click('text=新規作成');
    
    // フォームが表示されることを確認
    await expect(page.locator('input[name="name"]')).toBeVisible({ timeout: 10000 });
    await expect(page.locator('input[name="email"]')).toBeVisible({ timeout: 10000 });
    
    // ロール選択フィールドを確認
    await expect(page.locator('select')).toBeVisible({ timeout: 10000 });
    
    console.log('✅ User creation form accessible');
  });
});