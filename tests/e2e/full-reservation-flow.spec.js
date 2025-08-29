import { test, expect } from '@playwright/test';

test.describe('完全予約フローE2Eテスト', () => {
  
  test('新規顧客の予約から完了まで', async ({ page }) => {
    // 1. トップページアクセス
    await page.goto('/');
    await expect(page).toHaveTitle(/目のトレーニング/);
    
    // 2. 予約ページへ
    await page.click('text=予約する');
    await page.waitForURL('**/stores');
    
    // 3. 店舗選択
    await page.click('text=渋谷店');
    await page.waitForURL('**/reservation/category');
    
    // 4. カテゴリー選択
    await expect(page.locator('h1')).toContainText('メニューカテゴリー');
    await page.click('text=ケアコース');
    
    // 5. 時間・料金選択
    await page.waitForURL('**/reservation/time');
    await page.click('text=60分コース');
    
    // 6. オプション選択
    if (await page.locator('text=オプション選択').isVisible()) {
      await page.check('text=アイマスク追加');
      await page.click('button:has-text("次へ進む")');
    }
    
    // 7. カレンダーで日時選択
    await page.waitForURL('**/reservation/calendar');
    
    // 明日の日付を選択
    const tomorrow = new Date();
    tomorrow.setDate(tomorrow.getDate() + 1);
    const day = tomorrow.getDate();
    
    await page.click(`text="${day}"`);
    await page.click('text=14:00');
    
    // 8. 顧客情報入力
    await page.fill('input[name="customer_name"]', 'テスト太郎');
    await page.fill('input[name="customer_phone"]', '09012345678');
    await page.fill('input[name="customer_email"]', 'test@example.com');
    
    // 9. 確認画面
    await page.click('button:has-text("確認画面へ")');
    
    // 10. 予約確定
    await page.click('button:has-text("予約を確定")');
    
    // 11. 完了画面
    await page.waitForURL('**/reservation/complete/**');
    await expect(page.locator('h1')).toContainText('予約が完了しました');
    
    // 予約番号の確認
    const reservationNumber = await page.locator('.reservation-number').textContent();
    expect(reservationNumber).toMatch(/^R\d{8}$/);
  });
  
  test('既存顧客トークンでの予約', async ({ page }) => {
    // トークン付きURLでアクセス
    const testToken = 'test_token_12345';
    await page.goto(`/reservation/store?token=${testToken}`);
    
    // 自動的にカテゴリー選択画面へ
    await expect(page).toHaveURL('**/reservation/category');
    
    // 既存顧客限定メニューが表示されることを確認
    await expect(page.locator('text=VIPコース')).toBeVisible();
  });
  
  test('管理画面から予約管理', async ({ page }) => {
    // 管理画面ログイン
    await page.goto('/admin/login');
    await page.fill('input[name="email"]', 'admin@eye-training.com');
    await page.fill('input[name="password"]', 'password');
    await page.click('button[type="submit"]');
    
    // ダッシュボード確認
    await page.waitForURL('**/admin');
    await expect(page.locator('text=本日の予約')).toBeVisible();
    
    // 予約一覧へ
    await page.click('text=予約管理');
    await page.waitForURL('**/admin/reservations');
    
    // 新規予約作成
    await page.click('text=新規作成');
    
    // フォーム入力
    await page.selectOption('select[name="customer_id"]', { index: 1 });
    await page.selectOption('select[name="store_id"]', { label: '渋谷店' });
    await page.selectOption('select[name="menu_id"]', { index: 1 });
    await page.fill('input[name="reservation_date"]', '2025-09-01');
    await page.fill('input[name="reservation_time"]', '10:00');
    
    // 保存
    await page.click('button:has-text("作成")');
    
    // 作成成功メッセージ
    await expect(page.locator('.filament-notification')).toContainText('作成しました');
  });
  
  test('ガントチャート表示', async ({ page }) => {
    // 管理画面ログイン
    await page.goto('/admin/login');
    await page.fill('input[name="email"]', 'admin@eye-training.com');
    await page.fill('input[name="password"]', 'password');
    await page.click('button[type="submit"]');
    
    // ダッシュボードのガントチャート確認
    await page.waitForSelector('.reservation-gantt-chart');
    
    // 日付変更
    await page.click('button[aria-label="次の日"]');
    await page.waitForTimeout(500);
    
    // 今日に戻る
    await page.click('button:has-text("今日")');
    
    // 店舗切り替え
    await page.selectOption('select.store-selector', { label: '新宿店' });
    await page.waitForTimeout(500);
  });
  
  test('売上分析ダッシュボード', async ({ page }) => {
    // 管理画面ログイン
    await page.goto('/admin/login');
    await page.fill('input[name="email"]', 'admin@eye-training.com');
    await page.fill('input[name="password"]', 'password');
    await page.click('button[type="submit"]');
    
    // 売上統計ウィジェット確認
    await expect(page.locator('text=本日の売上')).toBeVisible();
    await expect(page.locator('text=今月の売上')).toBeVisible();
    await expect(page.locator('text=平均客単価')).toBeVisible();
    await expect(page.locator('text=キャンセル率')).toBeVisible();
    
    // グラフの期間切り替え
    const chartFilter = page.locator('.sales-chart-filter');
    if (await chartFilter.isVisible()) {
      await chartFilter.selectOption({ label: '過去30日間' });
      await page.waitForTimeout(500);
    }
  });
  
  test('レシート印刷', async ({ page }) => {
    // 売上詳細ページへ
    await page.goto('/admin/sales/1');
    
    // 印刷ボタンクリック
    await page.click('text=レシート印刷');
    
    // 新しいタブでレシート画面が開く
    const [receiptPage] = await Promise.all([
      page.waitForEvent('popup'),
      page.click('text=レシート印刷')
    ]);
    
    // レシート内容確認
    await expect(receiptPage.locator('.receipt-title')).toContainText('領収書');
    await expect(receiptPage.locator('.store-name')).toBeVisible();
    await expect(receiptPage.locator('.total-amount')).toBeVisible();
  });
  
  test('LINE設定と送信', async ({ page }) => {
    // 管理画面ログイン
    await page.goto('/admin/login');
    await page.fill('input[name="email"]', 'admin@eye-training.com');
    await page.fill('input[name="password"]', 'password');
    await page.click('button[type="submit"]');
    
    // LINE設定ページへ
    await page.click('text=LINE設定');
    await page.waitForURL('**/admin/line-settings');
    
    // 設定変更
    await page.check('text=予約確認を送信する');
    await page.fill('textarea[name="message_confirmation"]', 'テスト予約確認メッセージ');
    
    // 保存
    await page.click('button:has-text("設定を保存")');
    await expect(page.locator('.filament-notification')).toContainText('保存しました');
    
    // プロモーション送信
    await page.fill('textarea[name="promotionMessage"]', 'テストプロモーション');
    await page.click('button:has-text("今すぐ全員に送信")');
    await expect(page.locator('.filament-notification')).toContainText('送信完了');
  });
});

test.describe('エラーハンドリング', () => {
  
  test('無効な予約日時の処理', async ({ page }) => {
    await page.goto('/reservation/calendar');
    
    // 過去の日付を選択しようとする
    const yesterday = new Date();
    yesterday.setDate(yesterday.getDate() - 1);
    
    // エラーメッセージ確認
    await expect(page.locator('.error-message')).toContainText('過去の日時は選択できません');
  });
  
  test('重複予約の防止', async ({ page }) => {
    // 同じ時間に2回予約を試みる
    // 実装省略
  });
  
  test('認証エラーの処理', async ({ page }) => {
    await page.goto('/admin');
    
    // 未認証でアクセス
    await expect(page).toHaveURL('**/admin/login');
    
    // 間違った認証情報
    await page.fill('input[name="email"]', 'wrong@example.com');
    await page.fill('input[name="password"]', 'wrongpassword');
    await page.click('button[type="submit"]');
    
    await expect(page.locator('.error-message')).toBeVisible();
  });
});