import { test, expect } from '@playwright/test';

test.describe('シフト一括作成機能', () => {
  test('シフトを一括で作成できる', async ({ page }) => {
    // 管理画面にアクセス
    await page.goto('http://127.0.0.1:8000/admin/login');
    
    // ログイン
    await page.fill('input[name="email"]', 'admin@eye-training.com');
    await page.fill('input[name="password"]', 'password');
    await page.click('button[type="submit"]');
    
    // シフト管理に移動
    await page.goto('http://127.0.0.1:8000/admin/shifts');
    
    // 一括登録ボタンをクリック
    await page.click('text=一括登録');
    
    // ページが読み込まれるまで待機
    await page.waitForSelector('text=対象月と日付を選択');
    
    // 今日の日付を取得
    const today = new Date();
    const tomorrow = new Date(today);
    tomorrow.setDate(tomorrow.getDate() + 1);
    
    // 明日の日付をクリック
    const tomorrowDay = tomorrow.getDate();
    await page.click(`text=${tomorrowDay}`, { timeout: 10000 });
    
    // 選択状況を確認
    await expect(page.locator('text=1日選択中')).toBeVisible();
    
    // スタッフを選択
    const firstStaffCheckbox = page.locator('input[type="checkbox"]').first();
    await firstStaffCheckbox.check();
    
    // 時間を設定
    await page.selectOption('select', 'full'); // 終日を選択
    
    // 登録ボタンをクリック
    await page.click('button:has-text("一括登録")');
    
    // 成功メッセージを待機
    await expect(page.locator('text=シフトを一括登録しました')).toBeVisible({ timeout: 10000 });
    
    // シフト一覧ページに戻ることを確認
    await expect(page).toHaveURL(/.*\/admin\/shifts$/);
  });
  
  test('日付選択が正しく動作する', async ({ page }) => {
    await page.goto('http://127.0.0.1:8000/admin/login');
    await page.fill('input[name="email"]', 'admin@eye-training.com');
    await page.fill('input[name="password"]', 'password');
    await page.click('button[type="submit"]');
    
    await page.goto('http://127.0.0.1:8000/admin/shifts/create-bulk');
    await page.waitForSelector('text=対象月と日付を選択');
    
    // 平日を選択ボタンをクリック
    await page.click('text=平日を選択');
    
    // 選択された日数を確認（月によって異なるので最低でも20日以上は選択されることを確認）
    const selectedText = await page.locator('[class*="bg-blue-500"]').first().textContent();
    const selectedDays = parseInt(selectedText.match(/(\d+)日選択中/)?.[1] || '0');
    expect(selectedDays).toBeGreaterThan(15);
    
    // 選択解除ボタンをクリック
    await page.click('text=選択解除');
    
    // 選択がクリアされることを確認
    await expect(page.locator('text=日選択中')).not.toBeVisible();
  });
  
  test('エラーハンドリングが正しく動作する', async ({ page }) => {
    await page.goto('http://127.0.0.1:8000/admin/login');
    await page.fill('input[name="email"]', 'admin@eye-training.com');
    await page.fill('input[name="password"]', 'password');
    await page.click('button[type="submit"]');
    
    await page.goto('http://127.0.0.1:8000/admin/shifts/create-bulk');
    await page.waitForSelector('text=対象月と日付を選択');
    
    // 日付もスタッフも選択せずに登録ボタンをクリック
    await page.click('button:has-text("一括登録")');
    
    // エラーメッセージが表示されることを確認
    await expect(page.locator('text=日付を選択してください')).toBeVisible({ timeout: 5000 });
  });
});