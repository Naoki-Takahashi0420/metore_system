import { test, expect } from '@playwright/test';

test.describe('LINE簡単設定機能', () => {
  test.beforeEach(async ({ page }) => {
    // 管理画面にログイン
    await page.goto('http://127.0.0.1:8000/admin/login');
    await page.fill('input[type="email"]', 'admin@eye-training.com');
    await page.fill('input[type="password"]', 'password');
    await page.click('button[type="submit"]');
    await page.waitForURL('**/admin');
  });

  test('LINE簡単設定ページにアクセスできる', async ({ page }) => {
    // LINE管理メニューを確認
    await expect(page.locator('text=LINE管理')).toBeVisible();
    
    // LINE簡単設定をクリック
    await page.click('text=LINE簡単設定');
    
    // ページが表示されることを確認
    await expect(page.locator('h1:has-text("LINE簡単設定")')).toBeVisible();
  });

  test('4つの設定セクションが表示される', async ({ page }) => {
    await page.goto('http://127.0.0.1:8000/admin/simple-line-settings');
    
    // ① 予約確認セクション
    await expect(page.locator('text=① 予約確認')).toBeVisible();
    await expect(page.locator('text=予約完了時に自動送信')).toBeVisible();
    
    // ② リマインダーセクション
    await expect(page.locator('text=② リマインダー')).toBeVisible();
    await expect(page.locator('text=予約前に自動送信')).toBeVisible();
    
    // ③ プロモーションセクション
    await expect(page.locator('text=③ プロモーション一斉送信')).toBeVisible();
    await expect(page.locator('text=今すぐ送信 or 時間指定')).toBeVisible();
    
    // ④ 初回客フォローセクション
    await expect(page.locator('text=④ 初回客フォロー')).toBeVisible();
    await expect(page.locator('text=初回来店後、次の予約がない人に自動送信')).toBeVisible();
  });

  test('トグルスイッチが機能する', async ({ page }) => {
    await page.goto('http://127.0.0.1:8000/admin/simple-line-settings');
    
    // 予約確認トグルをテスト
    const confirmToggle = page.locator('input[name="send_confirmation"]').first();
    const initialState = await confirmToggle.isChecked();
    
    await confirmToggle.click();
    expect(await confirmToggle.isChecked()).toBe(!initialState);
    
    // 24時間前リマインダートグルをテスト
    const reminder24h = page.locator('input[name="reminder_24h"]').first();
    await reminder24h.click();
    expect(await reminder24h.isChecked()).toBe(!(await reminder24h.isChecked()));
  });

  test('プロモーションメッセージを入力できる', async ({ page }) => {
    await page.goto('http://127.0.0.1:8000/admin/simple-line-settings');
    
    // プロモーションセクションを展開
    const promotionSection = page.locator('text=③ プロモーション一斉送信');
    if (await promotionSection.isVisible()) {
      await promotionSection.click();
    }
    
    // メッセージを入力
    const messageTextarea = page.locator('textarea[name="promotion_message"]');
    await messageTextarea.fill('テストキャンペーンメッセージ\n今なら20%OFF！');
    
    // 入力内容を確認
    await expect(messageTextarea).toHaveValue('テストキャンペーンメッセージ\n今なら20%OFF！');
  });

  test('他のLINE管理機能にもアクセスできる', async ({ page }) => {
    await page.goto('http://127.0.0.1:8000/admin');
    
    // 店舗LINE設定
    await page.click('text=店舗LINE設定');
    await expect(page).toHaveURL(/.*store-line-settings/);
    
    // LINEリマインダールール
    await page.goto('http://127.0.0.1:8000/admin');
    await page.click('text=LINEリマインダールール');
    await expect(page).toHaveURL(/.*line-reminder-rules/);
    
    // メッセージテンプレート
    await page.goto('http://127.0.0.1:8000/admin');
    await page.click('text=メッセージテンプレート');
    await expect(page).toHaveURL(/.*line-message-templates/);
  });

  test('設定保存ボタンが表示される', async ({ page }) => {
    await page.goto('http://127.0.0.1:8000/admin/simple-line-settings');
    
    // 保存ボタンを確認
    const saveButton = page.locator('button:has-text("設定を保存")');
    await expect(saveButton).toBeVisible();
    
    // ボタンがクリック可能であることを確認
    await expect(saveButton).toBeEnabled();
  });
});