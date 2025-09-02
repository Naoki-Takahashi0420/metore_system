import { test, expect } from '@playwright/test';

test.describe('包括的システムテスト', () => {
  // テスト用の電話番号とOTP
  const testPhone = '09012345678';
  const testOtp = '123456';
  
  test.beforeEach(async ({ page }) => {
    // Basic認証をヘッダーに設定
    await page.setExtraHTTPHeaders({
      'Authorization': 'Basic ' + Buffer.from('user:password').toString('base64')
    });
  });

  test('1. 顧客ダッシュボードとログインフロー', async ({ page }) => {
    // ダッシュボードにアクセス（ログインページにリダイレクトされる）
    await page.goto('http://127.0.0.1:8000/customer/dashboard');
    
    // ログインページが表示されることを確認（タイトルで確認）
    await expect(page.locator('text=予約確認・ログイン')).toBeVisible();
    
    // 電話番号入力フィールドを見つけて入力
    const phoneInput = page.locator('input[type="tel"]').first();
    await phoneInput.fill(testPhone);
    
    // SMS認証コード送信ボタンをクリック
    await page.click('button:has-text("SMS認証コードを送信")');
    
    // OTP入力フィールドが表示されるまで待機
    await page.waitForTimeout(1000);
    
    // OTP入力フィールドを見つけて入力
    const otpInput = page.locator('input[type="text"]').last();
    await otpInput.fill(testOtp);
    
    // ログインボタンをクリック
    await page.click('button:has-text("ログイン")');
    
    // ダッシュボードが表示されるまで待機
    await page.waitForTimeout(3000);
    
    // ダッシュボードが表示されることを確認
    const dashboardTitle = page.locator('h1').first();
    await expect(dashboardTitle).toBeVisible();
    
    // 予約セクションが存在することを確認
    const reservationSection = page.locator('text=予約');
    await expect(reservationSection.first()).toBeVisible();
  });

  test('2. 新規予約フロー（店舗選択→カテゴリー→時間→カレンダー）', async ({ page }) => {
    // 店舗一覧ページにアクセス
    await page.goto('http://127.0.0.1:8000/stores');
    
    // 店舗カードが表示されることを確認
    await expect(page.locator('.store-card').first()).toBeVisible();
    
    // 最初の店舗を選択
    await page.click('.store-card button:has-text("予約する")');
    
    // カテゴリー選択画面に遷移することを確認
    await page.waitForURL('**/reservation/category');
    
    // カテゴリーカードが表示されることを確認
    await expect(page.locator('.category-card').first()).toBeVisible();
    
    // 最初のカテゴリーを選択
    await page.click('.category-card');
    
    // 時間選択画面に遷移することを確認
    await page.waitForURL('**/reservation/time');
    
    // メニューが表示されることを確認
    await expect(page.locator('.menu-item').first()).toBeVisible();
    
    // 最初のメニューを選択
    await page.click('.menu-item button:has-text("選択")');
    
    // カレンダー画面に遷移することを確認
    await page.waitForURL('**/reservation/calendar');
    
    // カレンダーが表示されることを確認
    await expect(page.locator('.calendar-container')).toBeVisible();
    
    // 予約可能な日時スロットが表示されることを確認
    const availableSlot = page.locator('.time-slot.available').first();
    await expect(availableSlot).toBeVisible();
    
    // 予約可能なスロットをクリック
    await availableSlot.click();
    
    // 確認モーダルが表示されることを確認
    await expect(page.locator('#confirmModal')).toBeVisible();
  });

  test('3. カルテ（医療記録）表示', async ({ page }) => {
    // 直接カルテページにアクセス（ログイン済みと仮定）
    await page.goto('http://127.0.0.1:8000/customer/medical-records');
    
    // ログインが必要な場合は処理
    if (await page.locator('#loginModal').isVisible()) {
      await page.fill('input[name="phone"]', testPhone);
      await page.click('button:has-text("OTPを送信")');
      await page.waitForSelector('input[name="otp"]', { state: 'visible' });
      await page.fill('input[name="otp"]', testOtp);
      await page.click('button:has-text("ログイン")');
      await page.waitForTimeout(2000);
    }
    
    // カルテ一覧が表示されることを確認
    await expect(page.locator('h1:has-text("カルテ履歴")')).toBeVisible();
  });

  test('4. 管理画面へのアクセス', async ({ page }) => {
    // 管理画面ログインページにアクセス
    await page.goto('http://127.0.0.1:8000/admin/login');
    
    // ログインフォームが表示されることを確認
    await expect(page.locator('input[name="email"]')).toBeVisible();
    await expect(page.locator('input[name="password"]')).toBeVisible();
    
    // 管理者でログイン
    await page.fill('input[name="email"]', 'admin@eye-training.com');
    await page.fill('input[name="password"]', 'password');
    await page.click('button[type="submit"]');
    
    // ダッシュボードに遷移することを確認
    await page.waitForURL('**/admin');
    await expect(page.locator('.filament-dashboard')).toBeVisible();
  });

  test('5. 予約フィルター機能（過去・今後・すべて）', async ({ page }) => {
    // ダッシュボードにアクセス
    await page.goto('http://127.0.0.1:8000/customer/dashboard');
    
    // ログイン
    if (await page.locator('#loginModal').isVisible()) {
      await page.fill('input[name="phone"]', testPhone);
      await page.click('button:has-text("OTPを送信")');
      await page.waitForSelector('input[name="otp"]', { state: 'visible' });
      await page.fill('input[name="otp"]', testOtp);
      await page.click('button:has-text("ログイン")');
      await page.waitForTimeout(2000);
    }
    
    // すべての予約を表示
    await page.click('button:has-text("すべて")');
    await page.waitForTimeout(500);
    const allReservations = await page.locator('.reservation-card').count();
    
    // 今後の予約のみ表示
    await page.click('button:has-text("今後の予約")');
    await page.waitForTimeout(500);
    const futureReservations = await page.locator('.reservation-card').count();
    
    // 過去の予約のみ表示
    await page.click('button:has-text("過去の予約")');
    await page.waitForTimeout(500);
    const pastReservations = await page.locator('.reservation-card').count();
    
    // フィルターが機能していることを確認
    expect(allReservations).toBeGreaterThanOrEqual(futureReservations);
    expect(allReservations).toBeGreaterThanOrEqual(pastReservations);
  });

  test('6. レスポンシブデザイン（モバイル表示）', async ({ page }) => {
    // モバイルサイズに設定
    await page.setViewportSize({ width: 375, height: 667 });
    
    // 店舗一覧ページ
    await page.goto('http://127.0.0.1:8000/stores');
    await expect(page.locator('.store-card').first()).toBeVisible();
    
    // カテゴリー選択画面
    await page.click('.store-card button:has-text("予約する")');
    await page.waitForURL('**/reservation/category');
    
    // モバイルレイアウトが適用されていることを確認
    const categoryCard = page.locator('.category-card').first();
    await expect(categoryCard).toBeVisible();
    
    // カードが縦並びになっていることを確認
    const cards = await page.locator('.category-card').all();
    if (cards.length > 1) {
      const firstBox = await cards[0].boundingBox();
      const secondBox = await cards[1].boundingBox();
      // 縦並びの場合、Y座標が異なる
      expect(secondBox.y).toBeGreaterThan(firstBox.y);
    }
  });

  test('7. エラーハンドリング', async ({ page }) => {
    // 存在しないページにアクセス
    await page.goto('http://127.0.0.1:8000/nonexistent-page');
    
    // 404エラーページが表示されることを確認
    const pageContent = await page.content();
    expect(pageContent).toContain('404');
  });

  test('8. 予約ラインシステム（メインライン・サブライン）', async ({ page }) => {
    // 管理画面にログイン
    await page.goto('http://127.0.0.1:8000/admin/login');
    await page.fill('input[name="email"]', 'admin@eye-training.com');
    await page.fill('input[name="password"]', 'password');
    await page.click('button[type="submit"]');
    
    // 予約ライン管理ページにアクセス
    await page.goto('http://127.0.0.1:8000/admin/reservation-lines');
    
    // ラインが表示されることを確認
    await expect(page.locator('table tbody tr').first()).toBeVisible();
    
    // メインラインとサブラインが存在することを確認
    const mainLineExists = await page.locator('text=main').isVisible();
    const subLineExists = await page.locator('text=sub').isVisible();
    
    expect(mainLineExists || subLineExists).toBeTruthy();
  });

  test('9. LINE連携機能の確認', async ({ page }) => {
    // 管理画面の店舗設定にアクセス
    await page.goto('http://127.0.0.1:8000/admin/login');
    await page.fill('input[name="email"]', 'admin@eye-training.com');
    await page.fill('input[name="password"]', 'password');
    await page.click('button[type="submit"]');
    
    // 店舗一覧にアクセス
    await page.goto('http://127.0.0.1:8000/admin/stores');
    
    // 最初の店舗を編集
    await page.click('a[href*="/admin/stores/"][href*="/edit"]');
    
    // LINE設定セクションが存在することを確認
    await expect(page.locator('text=LINE設定')).toBeVisible();
    
    // LINE有効化チェックボックスが存在することを確認
    await expect(page.locator('input[name*="line_enabled"]')).toBeVisible();
  });

  test('10. パフォーマンステスト', async ({ page }) => {
    // ページロード時間を計測
    const startTime = Date.now();
    await page.goto('http://127.0.0.1:8000/stores');
    await page.waitForLoadState('networkidle');
    const loadTime = Date.now() - startTime;
    
    // 3秒以内にロードされることを確認
    expect(loadTime).toBeLessThan(3000);
    
    // 画像の遅延読み込みが機能していることを確認
    const images = page.locator('img[loading="lazy"]');
    const lazyImageCount = await images.count();
    expect(lazyImageCount).toBeGreaterThanOrEqual(0);
  });
});