import { test, expect } from '@playwright/test';

test.describe('予約UIテスト', () => {
  test('予約ページのエラー確認', async ({ page }) => {
    console.log('📍 予約ページ（/reservation）にアクセス...');
    
    // エラーハンドリングを設定
    page.on('pageerror', error => {
      console.error('❌ ページエラー:', error.message);
    });
    
    page.on('console', msg => {
      if (msg.type() === 'error') {
        console.error('❌ コンソールエラー:', msg.text());
      }
    });
    
    // /reservationにアクセス
    const response = await page.goto('http://127.0.0.1:8000/reservation', {
      waitUntil: 'domcontentloaded',
      timeout: 10000
    });
    
    console.log('📊 ステータスコード:', response.status());
    
    if (response.status() === 500) {
      console.log('❌ 500エラーが発生しています');
      
      // エラーメッセージを取得
      const bodyText = await page.textContent('body');
      console.log('エラー内容の一部:', bodyText.substring(0, 500));
      
      // Laravel のエラーページかチェック
      const hasWhoopsError = await page.locator('.exception-message').count() > 0;
      if (hasWhoopsError) {
        const errorMessage = await page.locator('.exception-message').textContent();
        console.log('❌ Laravel エラー:', errorMessage);
      }
    } else {
      console.log('✅ ページが正常に読み込まれました');
      
      // ページタイトルを確認
      const title = await page.title();
      console.log('📄 ページタイトル:', title);
      
      // カレンダー形式の予約UIが表示されているか確認
      const hasTable = await page.locator('table.availability-table').count() > 0;
      if (hasTable) {
        console.log('✅ 予約時間テーブルが表示されています');
        
        // 時間枠の数を確認
        const timeSlots = await page.locator('.time-slot').count();
        console.log(`📊 利用可能な時間枠: ${timeSlots}個`);
      } else {
        console.log('⚠️ 予約時間テーブルが見つかりません');
      }
    }
  });
  
  test('旧予約ページ（/reservation/datetime）の確認', async ({ page }) => {
    console.log('📍 旧予約ページ（/reservation/datetime）にアクセス...');
    
    const response = await page.goto('http://127.0.0.1:8000/reservation/datetime', {
      waitUntil: 'networkidle',
      timeout: 10000
    });
    
    console.log('📊 ステータスコード:', response.status());
    
    if (response.status() === 200) {
      console.log('✅ 旧ページは正常に表示されています');
      
      // スクリーンショットを撮影
      await page.screenshot({ path: 'reservation-datetime.png', fullPage: true });
      console.log('📸 スクリーンショット保存: reservation-datetime.png');
      
      // カレンダーが表示されているか確認
      const hasCalendar = await page.locator('table').count() > 0;
      if (hasCalendar) {
        console.log('✅ カレンダーが表示されています');
      }
    }
  });
  
  test('コントローラーの問題診断', async ({ page }) => {
    console.log('\n🔍 問題診断開始...');
    
    // Laravelのルートデバッグページにアクセス
    const debugResponse = await page.goto('http://127.0.0.1:8000/_debugbar/open?op=get&id=latest', {
      timeout: 5000
    }).catch(() => null);
    
    if (debugResponse && debugResponse.status() === 200) {
      const debugData = await page.textContent('body');
      console.log('📊 デバッグ情報:', debugData.substring(0, 200));
    }
    
    console.log('\n💡 推奨される修正:');
    console.log('1. PublicReservationControllerが正しくインポートされているか確認');
    console.log('2. 必要なモデル（Store, Menu, Reservation）が存在するか確認');
    console.log('3. ビューファイルのパスが正しいか確認');
  });
});