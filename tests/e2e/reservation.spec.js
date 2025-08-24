import { test, expect } from '@playwright/test';

test.describe('予約システムテスト', () => {
  test('店舗選択ページの表示', async ({ page }) => {
    // トップページにアクセス
    await page.goto('/');
    
    // 予約開始ボタンをクリック（Hero Sectionの最も目立つボタン）
    await page.getByRole('link', { name: '今すぐ予約する' }).click();
    
    // 店舗選択ページ（正しいタイトルを確認）
    await expect(page.getByRole('heading', { name: '店舗を選択' })).toBeVisible();
    
    // ページが正しく表示されることを確認
    await expect(page.getByText('ご希望の店舗を選択してください')).toBeVisible();
  });

  test('顧客ログインページの表示', async ({ page }) => {
    // 顧客ログインページにアクセス
    await page.goto('/customer/login');
    
    // ページタイトルの確認
    await expect(page.getByRole('heading', { name: '予約確認・ログイン' })).toBeVisible();
    
    // 電話番号入力フィールドが表示されることを確認
    await expect(page.getByLabel('携帯電話番号')).toBeVisible();
    
    // SMS認証コード送信ボタンが表示されることを確認
    await expect(page.getByRole('button', { name: 'SMS認証コードを送信' })).toBeVisible();
  });
});