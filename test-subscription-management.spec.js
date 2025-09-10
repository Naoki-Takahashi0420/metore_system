const { test, expect } = require('@playwright/test');

test.describe('サブスクリプション管理機能', () => {
  test.beforeEach(async ({ page }) => {
    // 管理画面にログイン
    await page.goto('http://localhost:8002/admin/login');
    await page.fill('input[name="email"]', 'admin@eye-training.com');
    await page.fill('input[name="password"]', 'password');
    await page.click('button[type="submit"]');
    await page.waitForURL('**/admin');
  });

  test('ダッシュボードの要対応顧客ウィジェット表示', async ({ page }) => {
    // ダッシュボードに要対応顧客ウィジェットが表示されているか確認
    await expect(page.locator('text=要対応顧客')).toBeVisible();
    
    // 高橋直希様が決済失敗として表示されているか
    await expect(page.locator('text=高橋 直希')).toBeVisible();
    await expect(page.locator('text=🔴 決済失敗')).toBeVisible();
  });

  test('サブスクリプション管理画面へのアクセス', async ({ page }) => {
    // サブスク契約管理ページに移動
    await page.click('text=サブスク契約管理');
    await page.waitForURL('**/admin/customer-subscriptions');
    
    // ページタイトル確認
    await expect(page.locator('h1')).toContainText('サブスク契約');
    
    // 高橋直希様のレコードが表示されているか
    await expect(page.locator('text=高橋 直希')).toBeVisible();
    
    // ステータスバッジが正しく表示されているか
    const statusBadges = page.locator('[data-testid="badge"]');
    await expect(statusBadges.first()).toBeVisible();
  });

  test('決済失敗の切り替え機能', async ({ page }) => {
    // サブスク契約管理ページに移動
    await page.goto('http://localhost:8002/admin/customer-subscriptions');
    
    // 高橋直希様の行を探す
    const row = page.locator('tr').filter({ hasText: '高橋 直希' });
    await expect(row).toBeVisible();
    
    // 決済復旧ボタンをクリック（現在決済失敗状態のため）
    const actionButton = row.locator('button').filter({ hasText: '決済復旧' });
    if (await actionButton.isVisible()) {
      await actionButton.click();
      
      // モーダルが開くはず
      await page.waitForSelector('[role="dialog"]');
      
      // メモを入力
      await page.fill('textarea[name="payment_failed_notes"]', 'テスト用復旧メモ');
      
      // 保存ボタンクリック
      await page.click('button[type="submit"]');
      
      // 成功メッセージの確認
      await expect(page.locator('text=成功')).toBeVisible({ timeout: 10000 });
    }
  });

  test('休止機能のテスト', async ({ page }) => {
    // サブスク契約管理ページに移動
    await page.goto('http://localhost:8002/admin/customer-subscriptions');
    
    // テスト用の正常なサブスクリプションを探す
    const normalRow = page.locator('tr').filter({ hasText: '🟢 正常' }).first();
    
    if (await normalRow.isVisible()) {
      // 休止ボタンをクリック
      const pauseButton = normalRow.locator('button').filter({ hasText: '休止' });
      if (await pauseButton.isVisible()) {
        await pauseButton.click();
        
        // 確認モーダルが表示される
        await page.waitForSelector('[role="dialog"]');
        await expect(page.locator('text=サブスク休止の確認')).toBeVisible();
        
        // 確認ボタンをクリック
        await page.click('button', { hasText: '確認' });
        
        // 成功メッセージの確認
        await expect(page.locator('text=休止設定完了')).toBeVisible({ timeout: 10000 });
      }
    }
  });
});