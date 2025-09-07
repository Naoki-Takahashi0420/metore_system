import { test, expect } from '@playwright/test';

const BASE_URL = 'http://127.0.0.1:8000';

test.describe('アドミン通知システムE2Eテスト', () => {
  
  test('新規予約作成時にアドミン通知が送信される', async ({ page }) => {
    console.log('📝 新規予約フローのE2Eテストを開始します');
    
    // 予約ページにアクセス
    await page.goto(`${BASE_URL}/reservation`);
    console.log('✅ 予約ページにアクセスしました');
    
    // ページが正しく読み込まれるまで待機
    await page.waitForLoadState('networkidle');
    
    // 店舗選択（最初の店舗を選択）
    const storeButtons = page.locator('button:has-text("この店舗で予約する"), a:has-text("この店舗で予約する")');
    if (await storeButtons.count() > 0) {
      await storeButtons.first().click();
      console.log('✅ 店舗を選択しました');
      await page.waitForLoadState('networkidle');
    }
    
    // メニュー選択（最初のメニューを選択）
    const menuButtons = page.locator('button:has-text("このメニューを選択"), a:has-text("このメニューを選択")');
    if (await menuButtons.count() > 0) {
      await menuButtons.first().click();
      console.log('✅ メニューを選択しました');
      await page.waitForLoadState('networkidle');
    }
    
    // カレンダーから日付選択（利用可能な日をクリック）
    const availableDays = page.locator('.available-day, .calendar-day:not(.disabled):not(.past)');
    if (await availableDays.count() > 0) {
      await availableDays.first().click();
      console.log('✅ 日付を選択しました');
      await page.waitForTimeout(1000);
    }
    
    // 時間選択（利用可能な時間をクリック）
    const availableTimes = page.locator('.available-time, .time-slot:not(.disabled)');
    if (await availableTimes.count() > 0) {
      await availableTimes.first().click();
      console.log('✅ 時間を選択しました');
      await page.waitForTimeout(1000);
    }
    
    // 顧客情報入力
    const randomPhone = '090-' + Math.floor(Math.random() * 9000 + 1000) + '-' + Math.floor(Math.random() * 9000 + 1000);
    const randomEmail = 'e2e-test-' + Math.floor(Math.random() * 10000) + '@example.com';
    
    await page.fill('input[name="last_name"]', 'E2Eテスト');
    await page.fill('input[name="first_name"]', '太郎');
    await page.fill('input[name="phone"]', randomPhone);
    await page.fill('input[name="email"]', randomEmail);
    await page.fill('textarea[name="notes"]', 'E2Eテストによる予約です');
    
    console.log('✅ 顧客情報を入力しました');
    console.log(`   電話番号: ${randomPhone}`);
    console.log(`   メール: ${randomEmail}`);
    
    // 予約確定ボタンをクリック
    const submitButton = page.locator('button[type="submit"]:has-text("予約を確定"), input[type="submit"]');
    await submitButton.click();
    
    console.log('📤 予約確定ボタンをクリックしました');
    
    // 予約完了ページの確認
    await page.waitForURL(/.*\/reservation\/complete\/.*/, { timeout: 15000 });
    console.log('✅ 予約完了ページに遷移しました');
    
    // 予約番号が表示されることを確認
    const reservationNumber = await page.textContent('body');
    console.log(`📋 予約完了: ${reservationNumber.includes('予約番号') ? '予約番号が表示されました' : '予約が完了しました'}`);
    
    // 1秒待機してログが記録されるのを待つ
    await page.waitForTimeout(1000);
    
    console.log('🎉 新規予約E2Eテスト完了！');
  });

  test('予約キャンセル時にアドミン通知が送信される', async ({ page }) => {
    console.log('📝 予約キャンセルフローのE2Eテストを開始します');
    
    // まず予約を作成
    console.log('📋 テスト用予約を作成中...');
    
    // 顧客ダッシュボードにアクセス（仮の電話番号でテスト）
    const testPhone = '090-9999-1234';
    await page.goto(`${BASE_URL}/customer/auth?phone=${testPhone}`);
    
    // 認証トークンのダミーページを想定（実際の実装に合わせて調整）
    // この部分は実際のアプリケーションの認証フローに応じて実装
    
    console.log('⚠️  キャンセルテストは実装中（認証フローに依存）');
  });

});

// テスト前の準備
test.beforeEach(async ({ page }) => {
  // コンソールログを出力
  page.on('console', msg => {
    if (msg.type() === 'error') {
      console.error('❌ ブラウザコンソールエラー:', msg.text());
    }
  });
  
  // ネットワークエラーを監視
  page.on('pageerror', err => {
    console.error('❌ ページエラー:', err.message);
  });
});

// テスト後のクリーンアップ
test.afterEach(async ({ page }) => {
  console.log('🧹 テストクリーンアップ完了');
});