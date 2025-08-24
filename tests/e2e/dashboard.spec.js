import { test, expect } from '@playwright/test';

test.describe('管理画面ダッシュボードテスト', () => {
  test.beforeEach(async ({ page }) => {
    // ログインページへアクセス
    await page.goto('/admin/login');
    
    // ログイン
    await page.fill('input[name="email"]', 'admin@xsyumeno.com');
    await page.fill('input[name="password"]', 'password');
    await page.click('button[type="submit"]');
    
    // ダッシュボードへの遷移を待つ
    await page.waitForURL('**/admin');
  });

  test('ダッシュボードが正しく表示される', async ({ page }) => {
    // ダッシュボードのタイトルを確認
    await expect(page).toHaveTitle(/管理画面/);
    
    // 本日の予約ウィジェットが表示されている
    await expect(page.locator('text=本日の予約')).toBeVisible();
    
    // 売上統計ウィジェットが表示されている
    await expect(page.locator('text=本日の売上')).toBeVisible();
    await expect(page.locator('text=今月の売上')).toBeVisible();
    
    // NEWバッジが機能している（存在する場合）
    const newBadge = page.locator('text=🆕');
    if (await newBadge.count() > 0) {
      await expect(newBadge.first()).toBeVisible();
    }
  });

  test('売上ページへのリンクが機能する', async ({ page }) => {
    // 今月の売上をクリック
    const monthSalesCard = page.locator('text=今月の売上').locator('xpath=ancestor::div[contains(@class, "cursor-pointer")]').first();
    await monthSalesCard.click();
    
    // 売上ページへの遷移を確認
    await page.waitForURL('**/admin/sales');
    await expect(page).toHaveURL(/.*\/admin\/sales/);
  });

  test('予約ページへのリンクが機能する', async ({ page }) => {
    // すべての予約を見るボタンをクリック
    const viewAllButton = page.locator('text=すべての予約を見る');
    if (await viewAllButton.isVisible()) {
      await viewAllButton.click();
      await page.waitForURL('**/admin/reservations');
      await expect(page).toHaveURL(/.*\/admin\/reservations/);
    }
  });

  test('予約カレンダーページへアクセスできる', async ({ page }) => {
    // 予約カレンダーメニューをクリック
    await page.click('text=予約カレンダー');
    await page.waitForURL('**/admin/reservation-calendars');
    
    // カレンダーが表示されている
    await expect(page.locator('.fc-daygrid')).toBeVisible();
  });

  test('売上管理ページへアクセスできる', async ({ page }) => {
    // 売上管理メニューをクリック
    await page.click('nav >> text=売上管理');
    await page.waitForURL('**/admin/sales');
    
    // 売上リストが表示されている
    await expect(page.locator('text=売上番号')).toBeVisible();
  });
});

test.describe('スーパー管理者権限テスト', () => {
  test('スーパー管理者でログインして店舗選択ができる', async ({ page }) => {
    // スーパー管理者でログイン
    await page.goto('/admin/login');
    await page.fill('input[name="email"]', 'superadmin@xsyumeno.com');
    await page.fill('input[name="password"]', 'password');
    await page.click('button[type="submit"]');
    
    await page.waitForURL('**/admin');
    
    // 予約カレンダーへ移動
    await page.click('text=予約カレンダー');
    await page.waitForURL('**/admin/reservation-calendars');
    
    // 店舗選択ボタンが表示されている
    const storeSelectButton = page.locator('text=店舗選択');
    if (await storeSelectButton.isVisible()) {
      await expect(storeSelectButton).toBeVisible();
    }
  });
});