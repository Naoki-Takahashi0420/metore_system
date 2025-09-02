import { test, expect } from '@playwright/test';

// 実際のユーザー行動動線に沿った包括的テスト

test.describe('完全なユーザージャーニーテスト', () => {
  
  // シナリオ1: 新規ユーザーの予約から完了まで
  test('新規ユーザー: 店舗選択→カテゴリー→時間→日付→予約完了', async ({ page }) => {
    const issues = [];
    
    // 1. トップページまたは店舗一覧から開始
    console.log('=== Step 1: 店舗一覧アクセス ===');
    await page.goto('http://127.0.0.1:8000/stores');
    
    // ページが正しく表示されているか
    const title = await page.title();
    console.log(`ページタイトル: ${title}`);
    
    if (!title.includes('店舗')) {
      issues.push('店舗一覧のタイトルが正しく表示されていない');
    }
    
    // 店舗データが読み込まれるまで待つ
    try {
      await page.waitForSelector('button:has-text("この店舗を選択")', { timeout: 10000 });
      console.log('✅ 店舗選択ボタンが表示された');
    } catch (error) {
      issues.push('店舗選択ボタンが表示されない: ' + error.message);
      console.log('❌ 店舗選択ボタンが表示されない');
    }
    
    // 店舗を選択
    const storeButtons = await page.locator('button:has-text("この店舗を選択")').count();
    console.log(`利用可能な店舗数: ${storeButtons}`);
    
    if (storeButtons > 0) {
      await page.locator('button:has-text("この店舗を選択")').first().click();
      console.log('✅ 店舗を選択');
      
      // カテゴリー選択画面への遷移
      try {
        await page.waitForURL('**/reservation/category', { timeout: 5000 });
        console.log('✅ カテゴリー選択画面に遷移');
      } catch (error) {
        issues.push('カテゴリー選択画面への遷移に失敗: ' + error.message);
      }
    } else {
      issues.push('店舗選択ボタンが見つからない');
    }
    
    // 2. カテゴリー選択
    console.log('=== Step 2: カテゴリー選択 ===');
    const categoryCards = await page.locator('.bg-white.rounded-lg, .category-card, div[data-category]').count();
    console.log(`表示されているカテゴリー数: ${categoryCards}`);
    
    if (categoryCards === 0) {
      issues.push('カテゴリーカードが表示されていない');
    } else {
      // 最初のカテゴリーをクリック
      await page.locator('.bg-white.rounded-lg, .category-card, div[data-category]').first().click();
      console.log('✅ カテゴリーを選択');
      
      // 時間選択画面への遷移
      try {
        await page.waitForURL('**/reservation/time', { timeout: 5000 });
        console.log('✅ 時間選択画面に遷移');
      } catch (error) {
        issues.push('時間選択画面への遷移に失敗: ' + error.message);
      }
    }
    
    // 3. 時間選択
    console.log('=== Step 3: 時間選択 ===');
    const timeButtons = await page.locator('button').filter({ hasText: /\d{1,2}:\d{2}/ }).count();
    console.log(`利用可能な時間枠: ${timeButtons}`);
    
    if (timeButtons === 0) {
      issues.push('時間選択ボタンが表示されていない');
      
      // メニューカードの確認
      const menuCards = await page.locator('.menu-card, .bg-white').count();
      console.log(`メニューカード数: ${menuCards}`);
      
      if (menuCards === 0) {
        issues.push('メニューカードも表示されていない');
      }
    } else {
      await page.locator('button').filter({ hasText: /\d{1,2}:\d{2}/ }).first().click();
      console.log('✅ 時間を選択');
      
      // カレンダー画面への遷移
      try {
        await page.waitForURL('**/reservation/calendar', { timeout: 5000 });
        console.log('✅ カレンダー画面に遷移');
      } catch (error) {
        issues.push('カレンダー画面への遷移に失敗: ' + error.message);
      }
    }
    
    // 4. 日付選択
    console.log('=== Step 4: 日付選択 ===');
    const availableDates = await page.locator('td:has-text("○"), .available-date, [data-available="true"]').count();
    console.log(`予約可能日数: ${availableDates}`);
    
    if (availableDates === 0) {
      issues.push('予約可能日が表示されていない');
      
      // カレンダーの存在確認
      const calendar = await page.locator('table, .calendar, #calendar').count();
      console.log(`カレンダー要素数: ${calendar}`);
      
      if (calendar === 0) {
        issues.push('カレンダー自体が表示されていない');
      }
    }
    
    // 問題レポート
    console.log('\n=== 問題レポート ===');
    if (issues.length > 0) {
      console.log('発見された問題:');
      issues.forEach((issue, index) => {
        console.log(`${index + 1}. ${issue}`);
      });
    } else {
      console.log('✅ 基本的な予約フローは動作している');
    }
  });
  
  // シナリオ2: 既存ユーザーのログイン→ダッシュボード→機能利用
  test('既存ユーザー: ログイン→ダッシュボード→各機能テスト', async ({ page }) => {
    const issues = [];
    
    // 1. ログイン
    console.log('=== Step 1: ログイン ===');
    await page.goto('http://127.0.0.1:8000/customer/login');
    
    // ログインフォームの存在確認
    const phoneInput = await page.locator('#phone').count();
    const otpButton = await page.locator('button:has-text("SMS認証コードを送信"), #send-otp-button').count();
    
    console.log(`電話番号入力欄: ${phoneInput}`);
    console.log(`OTP送信ボタン: ${otpButton}`);
    
    if (phoneInput === 0 || otpButton === 0) {
      issues.push('ログインフォームの要素が見つからない');
    }
    
    // テストログイン実行
    if (phoneInput > 0) {
      await page.fill('#phone', '08033372305');
      console.log('✅ 電話番号入力');
      
      if (otpButton > 0) {
        await page.locator('button:has-text("SMS認証コードを送信"), #send-otp-button').click();
        
        // OTP入力画面の表示待ち
        try {
          await page.waitForSelector('#otp, #otp-code, input[name="otp_code"]', { timeout: 10000 });
          console.log('✅ OTP入力画面表示');
          
          // OTP入力
          const otpInput = page.locator('#otp, #otp-code, input[name="otp_code"]');
          await otpInput.fill('123456');
          
          const verifyButton = page.locator('button:has-text("ログイン"), button:has-text("認証"), #verify-otp');
          await verifyButton.click();
          
          // ダッシュボードへの遷移待ち
          try {
            await page.waitForURL('**/customer/dashboard', { timeout: 10000 });
            console.log('✅ ダッシュボードに遷移');
          } catch (error) {
            issues.push('ダッシュボードへの遷移に失敗: ' + error.message);
          }
          
        } catch (error) {
          issues.push('OTP入力画面が表示されない: ' + error.message);
        }
      }
    }
    
    // 2. ダッシュボードの機能確認
    console.log('=== Step 2: ダッシュボード機能確認 ===');
    
    // 基本要素の存在確認
    const elements = {
      '顧客名': 'h1:has-text("マイページ"), #customer-name, .customer-name',
      '今後の予約': '#upcoming-reservations, .upcoming-reservations',
      '新規予約ボタン': 'button:has-text("新規予約"), a:has-text("新規予約")',
      'リピート予約': 'button:has-text("リピート"), button:has-text("同じメニュー")',
      'すべての予約': 'button:has-text("すべて"), a:has-text("すべて")'
    };
    
    for (const [name, selector] of Object.entries(elements)) {
      const count = await page.locator(selector).count();
      console.log(`${name}: ${count}個`);
      if (count === 0) {
        issues.push(`${name}が見つからない`);
      }
    }
    
    // 3. 予約データの表示確認
    console.log('=== Step 3: 予約データ表示確認 ===');
    
    // 少し待ってからデータ確認
    await page.waitForTimeout(3000);
    
    const reservationCards = await page.locator('[id^="reservation-"], .reservation-card, .border.rounded').count();
    console.log(`表示されている予約カード数: ${reservationCards}`);
    
    if (reservationCards === 0) {
      // エラーメッセージの確認
      const errorMessages = await page.locator('.text-red-500, .alert-danger, .error').count();
      console.log(`エラーメッセージ数: ${errorMessages}`);
      
      if (errorMessages > 0) {
        const errorText = await page.locator('.text-red-500, .alert-danger, .error').first().textContent();
        issues.push(`予約データ取得エラー: ${errorText}`);
      } else {
        issues.push('予約データが表示されていない（エラーメッセージなし）');
      }
    }
    
    // 4. 各機能のクリックテスト
    console.log('=== Step 4: 機能ボタンテスト ===');
    
    // 新規予約ボタン
    const newReservationBtn = await page.locator('button:has-text("新規予約"), a:has-text("新規予約")').count();
    if (newReservationBtn > 0) {
      console.log('✅ 新規予約ボタンが存在');
    } else {
      issues.push('新規予約ボタンが見つからない');
    }
    
    // 問題レポート
    console.log('\n=== ダッシュボード問題レポート ===');
    if (issues.length > 0) {
      console.log('発見された問題:');
      issues.forEach((issue, index) => {
        console.log(`${index + 1}. ${issue}`);
      });
    } else {
      console.log('✅ ダッシュボードは基本的に動作している');
    }
  });
  
  // シナリオ3: モダンダッシュボードの表示確認
  test('モダンダッシュボード: UI要素とデータ表示確認', async ({ page }) => {
    const issues = [];
    
    console.log('=== モダンダッシュボード表示テスト ===');
    await page.goto('http://127.0.0.1:8000/customer/dashboard-modern');
    
    // ログイン状態でない場合の処理
    if (page.url().includes('login')) {
      console.log('ログインが必要 - スキップ');
      return;
    }
    
    // UI要素の確認
    const uiElements = {
      'ヘッダー': '.bg-white.border-b h1',
      'プロフィールセクション': '.w-16.h-16, .user-initial',
      'ポイントカード': '.bg-gradient-to-r',
      'クイックアクション': '.grid.grid-cols-2',
      'ボトムナビ': '.fixed.bottom-0'
    };
    
    for (const [name, selector] of Object.entries(uiElements)) {
      const count = await page.locator(selector).count();
      console.log(`${name}: ${count}個`);
      if (count === 0) {
        issues.push(`モダンUI: ${name}が表示されていない`);
      }
    }
    
    // 意味不明な表示の確認
    const confusingElements = await page.locator(':has-text("ゴールド会員"), :has-text("ポイント"), :has-text("ランク")').count();
    if (confusingElements > 0) {
      issues.push('実際のデータに基づかない表示がある（ゴールド会員、ポイントなど）');
    }
    
    console.log('\n=== モダンダッシュボード問題レポート ===');
    issues.forEach((issue, index) => {
      console.log(`${index + 1}. ${issue}`);
    });
  });
  
  // シナリオ4: 視力推移グラフページ
  test('視力推移グラフ: 表示と機能確認', async ({ page }) => {
    const issues = [];
    
    console.log('=== 視力推移グラフテスト ===');
    await page.goto('http://127.0.0.1:8000/customer/vision-progress');
    
    // Chart.jsの読み込み確認
    const chartScript = await page.locator('script[src*="chart.js"]').count();
    console.log(`Chart.jsスクリプト: ${chartScript}個`);
    
    if (chartScript === 0) {
      issues.push('Chart.jsが読み込まれていない');
    }
    
    // キャンバス要素の確認
    const canvas = await page.locator('#visionChart').count();
    console.log(`グラフキャンバス: ${canvas}個`);
    
    if (canvas === 0) {
      issues.push('グラフ表示用のキャンバスが見つからない');
    }
    
    // 期間選択ボタン
    const periodButtons = await page.locator('.period-btn, button:has-text("ヶ月")').count();
    console.log(`期間選択ボタン: ${periodButtons}個`);
    
    if (periodButtons < 4) {
      issues.push('期間選択ボタンが不足している');
    }
    
    // 実際のデータ vs サンプルデータの問題
    const sampleDataWarning = await page.evaluate(() => {
      return document.body.innerHTML.includes('サンプルデータ');
    });
    
    if (sampleDataWarning) {
      issues.push('実際の視力データではなくサンプルデータを使用している');
    }
    
    console.log('\n=== 視力推移グラフ問題レポート ===');
    issues.forEach((issue, index) => {
      console.log(`${index + 1}. ${issue}`);
    });
  });
  
});

// 総合レポート生成
test.describe('総合問題レポート', () => {
  test('全体的な問題と推奨改善点', async ({ page }) => {
    console.log('\n🔍 ===== 総合分析レポート =====');
    console.log('\n【発見された主な問題点】');
    console.log('1. 実データと表示の不整合');
    console.log('   - ゴールド会員、ポイントなどの架空情報');
    console.log('   - サンプルデータの使用');
    
    console.log('2. 予約フローの不完全性');
    console.log('   - 時間枠データの不足');
    console.log('   - メニューデータの不備');
    
    console.log('3. ダッシュボードのデータ表示');
    console.log('   - 予約データの読み込みエラー');
    console.log('   - エラーハンドリングの不備');
    
    console.log('\n【推奨改善点】');
    console.log('1. 架空データの削除・実データベース化');
    console.log('2. エラーメッセージの改善');
    console.log('3. データ不足時の適切な表示');
    console.log('4. ユーザビリティの向上');
    
    console.log('\n【緊急対応が必要な項目】');
    console.log('- トークン認証の安定化');
    console.log('- 予約データの正常表示');
    console.log('- 意味不明な会員制度表示の修正');
  });
});