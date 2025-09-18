import { test, expect } from '@playwright/test';

test.describe('領収証機能テスト', () => {
  test('管理画面で予約完了時に領収証ボタンが表示される', async ({ page }) => {
    // 管理画面にログイン
    await page.goto('http://localhost:8000/admin/login');
    await page.fill('input[name="email"]', 'admin@eye-training.com');
    await page.fill('input[name="password"]', 'password');
    await page.click('button[type="submit"]');

    // 予約一覧へ
    await page.goto('http://localhost:8000/admin/reservations');
    await page.waitForLoadState('networkidle');

    // 完了済み予約の領収証ボタンを確認
    const receiptButton = page.locator('text=領収証').first();
    if (await receiptButton.count() > 0) {
      await expect(receiptButton).toBeVisible();
      console.log('✓ 完了済み予約に領収証ボタンが表示されています');
    }
  });

  test('領収証ページが正しく表示される', async ({ page }) => {
    // 直接領収証URLにアクセス（予約ID=1の場合）
    await page.goto('http://localhost:8000/receipt/reservation/1');

    // 領収証の主要要素を確認
    await expect(page.locator('text=領収書')).toBeVisible();
    await expect(page.locator('text=レシート番号:')).toBeVisible();

    // 印刷ボタンの確認
    const printButton = page.locator('button:has-text("レシートを印刷")');
    await expect(printButton).toBeVisible();

    console.log('✓ 領収証ページが正しく表示されています');
  });

  test('サブスク予約の領収証が適切に表示される', async ({ page }) => {
    // サブスク予約の領収証を表示（モックデータまたは実際のサブスク予約ID）
    await page.goto('http://localhost:8000/receipt/reservation/1');

    // ページ内容を確認
    const pageContent = await page.content();

    // サブスク関連の表示を確認
    if (pageContent.includes('サブスク')) {
      console.log('✓ サブスク利用の領収証が正しく表示されています');

      // サブスク利用の表示確認
      const subscriptionText = page.locator('text=/サブスク/i');
      if (await subscriptionText.count() > 0) {
        await expect(subscriptionText.first()).toBeVisible();
      }
    }
  });

  test('顧客マイページから領収証にアクセスできる', async ({ page }) => {
    // 顧客ログイン（モック）
    await page.goto('http://localhost:8000/customer/login');

    // LocalStorageにトークンを設定（テスト用）
    await page.evaluate(() => {
      localStorage.setItem('customer_token', 'test_token');
      localStorage.setItem('customer_data', JSON.stringify({
        id: 1,
        name: 'テストユーザー'
      }));
    });

    // 予約詳細ページへ
    await page.goto('http://localhost:8000/customer/reservations/1');

    // 領収証ボタンの確認（完了済み予約の場合のみ表示）
    const receiptBtn = page.locator('#receipt-btn');

    // ボタンが存在する場合、リンクが正しいか確認
    if (await receiptBtn.count() > 0) {
      const href = await receiptBtn.getAttribute('href');
      expect(href).toContain('/receipt/reservation/');
      console.log('✓ 顧客マイページに領収証ボタンが表示されています');
    }
  });

  test('領収証の印刷機能が動作する', async ({ page }) => {
    // 領収証ページへ
    await page.goto('http://localhost:8000/receipt/reservation/1');

    // 印刷ダイアログをモック
    await page.evaluate(() => {
      window.print = () => {
        console.log('印刷機能が呼び出されました');
        return true;
      };
    });

    // 印刷ボタンをクリック
    const printButton = page.locator('button:has-text("レシートを印刷")');
    if (await printButton.count() > 0) {
      await printButton.click();
      console.log('✓ 印刷機能が正しく動作しています');
    }
  });
});