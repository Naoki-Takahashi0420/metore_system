import { test, expect } from '@playwright/test';

test.describe('管理画面テスト', () => {
  test('管理画面ログインテスト', async ({ page }) => {
    // 管理画面にアクセス
    await page.goto('/admin');
    
    // ログインページにリダイレクトされることを確認
    await expect(page).toHaveURL(/.*\/admin\/login/);
    
    // ログインフォームが表示されることを確認
    await expect(page.getByRole('heading', { name: 'ログイン' })).toBeVisible();
    
    // メールアドレスとパスワードフィールドが存在することを確認
    await expect(page.getByLabel('メールアドレス')).toBeVisible();
    await expect(page.getByLabel('パスワード')).toBeVisible();
    
    // ログイン実行
    await page.getByLabel('メールアドレス').fill('superadmin@xsyumeno.com');
    await page.getByLabel('パスワード').fill('password');
    await page.getByRole('button', { name: 'ログイン' }).click();
    
    // ダッシュボードにリダイレクトされることを確認
    await expect(page).toHaveURL(/.*\/admin$/);
    
    // ダッシュボードのタイトルが表示されることを確認
    await expect(page.getByRole('heading', { name: 'ダッシュボード' })).toBeVisible();
  });

  test('顧客管理画面テスト', async ({ page }) => {
    // ログイン
    await page.goto('/admin/login');
    await page.getByLabel('メールアドレス').fill('superadmin@xsyumeno.com');
    await page.getByLabel('パスワード').fill('password');
    await page.getByRole('button', { name: 'ログイン' }).click();
    
    // 顧客管理ページにアクセス
    await page.getByRole('link', { name: '顧客管理' }).click();
    
    // 顧客一覧が表示されることを確認
    await expect(page.getByRole('heading', { name: '顧客' })).toBeVisible();
    
    // テーブルが表示されることを確認
    await expect(page.getByRole('table')).toBeVisible();
    
    // データが表示されているかチェック
    const tableRows = page.getByRole('row');
    const rowCount = await tableRows.count();
    console.log(`顧客テーブル行数: ${rowCount}`);
    
    // ヘッダー行を除いて1行以上あることを確認（データが存在する場合）
    if (rowCount > 1) {
      await expect(tableRows.nth(1)).toBeVisible();
    } else {
      console.log('顧客データが見つかりません');
    }
  });

  test('予約管理画面テスト', async ({ page }) => {
    // ログイン
    await page.goto('/admin/login');
    await page.getByLabel('メールアドレス').fill('superadmin@xsyumeno.com');
    await page.getByLabel('パスワード').fill('password');
    await page.getByRole('button', { name: 'ログイン' }).click();
    
    // 予約管理ページにアクセス
    await page.getByRole('link', { name: '予約管理' }).click();
    
    // 予約一覧が表示されることを確認
    await expect(page.getByRole('heading', { name: '予約' })).toBeVisible();
    
    // テーブルが表示されることを確認
    await expect(page.getByRole('table')).toBeVisible();
    
    // データが表示されているかチェック
    const tableRows = page.getByRole('row');
    const rowCount = await tableRows.count();
    console.log(`予約テーブル行数: ${rowCount}`);
    
    // ヘッダー行を除いて1行以上あることを確認（データが存在する場合）
    if (rowCount > 1) {
      await expect(tableRows.nth(1)).toBeVisible();
    } else {
      console.log('予約データが見つかりません');
    }
  });
});