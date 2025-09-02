import { test, expect } from '@playwright/test';

// テスト用の認証情報
const TEST_PHONE = '08033372305';
const TEST_OTP = '123456';

test.describe('Customer Dashboard Comprehensive Tests', () => {
  
  // ログイン処理を共通化
  async function loginAsCustomer(page) {
    await page.goto('/customer/login');
    
    // 電話番号入力
    await page.fill('#phone', TEST_PHONE);
    await page.click('#send-otp-button');
    
    // OTP送信成功を待つ
    await page.waitForSelector('#otp-section', { state: 'visible' });
    
    // OTP入力
    await page.fill('#otp-code', TEST_OTP);
    await page.click('#verify-otp');
    
    // ダッシュボードへのリダイレクトを待つ
    await page.waitForURL('**/customer/dashboard', { timeout: 10000 });
  }
  
  test('ログインフロー - OTP認証', async ({ page }) => {
    await page.goto('/customer/login');
    
    // 電話番号入力
    await page.fill('#phone', TEST_PHONE);
    await page.click('#send-otp-button');
    
    // OTP入力画面が表示されることを確認
    await expect(page.locator('#otp-section')).toBeVisible();
    
    // OTP入力
    await page.fill('#otp-code', TEST_OTP);
    await page.click('#verify-otp');
    
    // ダッシュボードに遷移することを確認
    await page.waitForURL('**/customer/dashboard');
    await expect(page).toHaveURL(/.*customer\/dashboard/);
    
    // localStorageにトークンが保存されていることを確認
    const token = await page.evaluate(() => localStorage.getItem('customer_token'));
    expect(token).toBeTruthy();
  });
  
  test('ダッシュボード表示 - 全要素の確認', async ({ page }) => {
    await loginAsCustomer(page);
    
    // メインタイトル
    await expect(page.locator('h1').filter({ hasText: 'マイページ' })).toBeVisible();
    
    // 3つのメインセクションが表示されることを確認
    await expect(page.locator('text=予約する')).toBeVisible();
    await expect(page.locator('text=予約を変更・キャンセルする')).toBeVisible();
    await expect(page.locator('text=カルテを見る')).toBeVisible();
    
    // 予約履歴が読み込まれることを確認
    await page.waitForFunction(() => {
      const reservations = document.querySelectorAll('[id^="reservation-"]');
      return reservations.length > 0;
    }, { timeout: 10000 });
  });
  
  test('予約一覧表示とフィルタリング', async ({ page }) => {
    await loginAsCustomer(page);
    
    // 予約一覧が表示されるまで待つ
    await page.waitForSelector('[id^="reservation-"]', { timeout: 10000 });
    
    // フィルタタブをテスト
    await page.click('button:has-text("すべて")');
    const allReservations = await page.locator('[id^="reservation-"]').count();
    expect(allReservations).toBeGreaterThan(0);
    
    await page.click('button:has-text("今後の予約")');
    await page.waitForTimeout(500);
    const futureReservations = await page.locator('[id^="reservation-"]').count();
    
    await page.click('button:has-text("過去の予約")');
    await page.waitForTimeout(500);
    const pastReservations = await page.locator('[id^="reservation-"]').count();
    
    // すべての予約 = 今後 + 過去
    expect(allReservations).toBe(futureReservations + pastReservations);
  });
  
  test('24時間ルール - キャンセル制限', async ({ page }) => {
    await loginAsCustomer(page);
    
    // 今後の予約タブを選択
    await page.click('button:has-text("今後の予約")');
    await page.waitForTimeout(500);
    
    // 最初の予約カードを取得
    const firstReservation = page.locator('[id^="reservation-"]').first();
    
    // キャンセルボタンをクリック
    await firstReservation.locator('button:has-text("キャンセル")').click();
    
    // 確認ダイアログを待つ
    page.on('dialog', async dialog => {
      expect(dialog.message()).toContain('キャンセルしますか');
      await dialog.accept();
    });
    
    // APIレスポンスを待つ
    const response = await page.waitForResponse(
      response => response.url().includes('/api/customer/reservations/') && 
                 response.url().includes('/cancel'),
      { timeout: 10000 }
    );
    
    const responseData = await response.json();
    
    // 24時間以内の場合は電話連絡が必要
    if (!responseData.success && responseData.require_phone_contact) {
      // 電話連絡モーダルが表示されることを確認
      await expect(page.locator('#phone-contact-modal')).toBeVisible();
      await expect(page.locator('#phone-contact-modal')).toContainText('電話でのご連絡が必要です');
    }
  });
  
  test('同じメニューで予約 - クイック予約機能', async ({ page }) => {
    await loginAsCustomer(page);
    
    // 過去の予約タブを選択
    await page.click('button:has-text("過去の予約")');
    await page.waitForTimeout(500);
    
    // 最初の過去予約の「同じメニューで予約」ボタンをクリック
    const pastReservation = page.locator('[id^="reservation-"]').first();
    await pastReservation.locator('button:has-text("同じメニューで予約")').click();
    
    // 予約ページに遷移することを確認
    await expect(page).toHaveURL(/.*reservation/);
    
    // セッションストレージにメニュー情報が保存されていることを確認
    const menuData = await page.evaluate(() => sessionStorage.getItem('selected_menu'));
    expect(menuData).toBeTruthy();
  });
  
  test('モバイルレスポンシブ - スマホ表示確認', async ({ page }) => {
    // iPhone 12のビューポートに設定
    await page.setViewportSize({ width: 390, height: 844 });
    
    await loginAsCustomer(page);
    
    // モバイルでも全要素が表示されることを確認
    await expect(page.locator('h1').filter({ hasText: 'マイページ' })).toBeVisible();
    
    // カードレイアウトが縦並びになっていることを確認
    const cards = page.locator('.bg-white.rounded-lg.shadow-md');
    const firstCard = await cards.first().boundingBox();
    const secondCard = await cards.nth(1).boundingBox();
    
    if (firstCard && secondCard) {
      // モバイルでは縦並びなのでY座標が異なる
      expect(secondCard.y).toBeGreaterThan(firstCard.y + firstCard.height);
    }
    
    // 予約カードが適切に表示されることを確認
    await page.waitForSelector('[id^="reservation-"]');
    const reservationCard = page.locator('[id^="reservation-"]').first();
    await expect(reservationCard).toBeVisible();
    
    // ボタンがタップ可能なサイズであることを確認
    const cancelButton = reservationCard.locator('button:has-text("キャンセル")');
    const buttonBox = await cancelButton.boundingBox();
    if (buttonBox) {
      expect(buttonBox.height).toBeGreaterThanOrEqual(44); // iOS推奨タップサイズ
    }
  });
  
  test('予約詳細表示', async ({ page }) => {
    await loginAsCustomer(page);
    
    // 最初の予約カードをクリック
    const firstReservation = page.locator('[id^="reservation-"]').first();
    await firstReservation.click();
    
    // 詳細ページへの遷移を確認
    await page.waitForURL('**/customer/reservations/*');
    
    // 詳細情報が表示されることを確認
    await expect(page.locator('text=予約詳細')).toBeVisible();
  });
  
  test('カルテ表示機能', async ({ page }) => {
    await loginAsCustomer(page);
    
    // カルテセクションまでスクロール
    await page.locator('text=カルテを見る').scrollIntoViewIfNeeded();
    
    // カルテ一覧を表示ボタンをクリック
    await page.click('text=カルテ一覧を表示');
    
    // カルテページへの遷移を確認
    await expect(page).toHaveURL(/.*customer\/medical-records/);
  });
  
  test('エラーハンドリング - API失敗時', async ({ page }) => {
    await page.goto('/customer/login');
    
    // 不正な電話番号でテスト
    await page.fill('#phone', '00000000000');
    await page.click('#send-otp');
    
    // エラーメッセージが表示されることを確認
    await expect(page.locator('.alert-danger')).toBeVisible();
  });
  
  test('セッションタイムアウト処理', async ({ page }) => {
    await loginAsCustomer(page);
    
    // トークンを削除してセッション切れをシミュレート
    await page.evaluate(() => {
      localStorage.removeItem('customer_token');
    });
    
    // ページをリロード
    await page.reload();
    
    // ログインページにリダイレクトされることを確認
    await page.waitForURL('**/customer/login', { timeout: 10000 });
  });
});

test.describe('予約変更機能', () => {
  async function loginAsCustomer(page) {
    await page.goto('/customer/login');
    await page.fill('#phone', TEST_PHONE);
    await page.click('#send-otp-button');
    await page.waitForSelector('#otp-section', { state: 'visible' });
    await page.fill('#otp-code', TEST_OTP);
    await page.click('#verify-otp');
    await page.waitForURL('**/customer/dashboard', { timeout: 10000 });
  }
  
  test('予約変更 - 日時変更', async ({ page }) => {
    await loginAsCustomer(page);
    
    // 今後の予約を選択
    await page.click('button:has-text("今後の予約")');
    await page.waitForTimeout(500);
    
    const firstReservation = page.locator('[id^="reservation-"]').first();
    
    // 変更ボタンをクリック
    await firstReservation.locator('button:has-text("変更")').click();
    
    // 変更モーダルが表示されることを確認
    await expect(page.locator('#change-modal')).toBeVisible();
    
    // 新しい日時を選択（実装に応じて調整）
    // await page.selectOption('#new-date', '2025-09-01');
    // await page.selectOption('#new-time', '14:00');
    
    // 変更を確定
    // await page.click('#confirm-change');
  });
});

test.describe('Performance Tests', () => {
  test('ダッシュボード読み込み速度', async ({ page }) => {
    const startTime = Date.now();
    
    await page.goto('/customer/dashboard');
    
    // 主要コンテンツの読み込み完了を待つ
    await page.waitForSelector('[id^="reservation-"]', { timeout: 10000 });
    
    const loadTime = Date.now() - startTime;
    
    // 3秒以内に読み込まれることを確認
    expect(loadTime).toBeLessThan(3000);
  });
});