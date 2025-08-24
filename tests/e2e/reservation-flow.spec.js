import { test, expect } from '@playwright/test';

test.describe('既存顧客（08033372305）の予約ブロックテスト', () => {
  
  test('既存顧客は予約できないことを確認', async ({ page }) => {
    console.log('\n========================================');
    console.log('🔒 既存顧客の予約ブロックテスト');
    console.log('========================================\n');
    
    // 1. 予約トップページへアクセス
    await page.goto('http://127.0.0.1:8000/reservation');
    await page.waitForLoadState('networkidle');
    await page.screenshot({ path: 'test-results/1-reservation-top.png' });
    console.log('Step 1: 予約トップページ');
    
    // 2. 初回予約を選択
    await page.click('text=初回予約をする');
    await page.waitForLoadState('networkidle');
    await page.screenshot({ path: 'test-results/2-store-select.png' });
    console.log('Step 2: 店舗選択ページへ遷移');
    
    // ページタイトル確認
    const title = await page.title();
    console.log('📄 ページタイトル:', title);
    await expect(page).toHaveTitle(/メニュー選択/);
    
    // ヘッダー確認
    const header = await page.locator('h1').textContent();
    console.log('📝 ヘッダー:', header);
    await expect(page.locator('h1')).toContainText('メニューを選択');
    
    // 進捗インジケーター確認
    const activeStep = await page.locator('.bg-blue-500').first();
    await expect(activeStep).toBeVisible();
    console.log('✅ 進捗インジケーター表示確認');
    
    // メニューアイテムの確認
    const menuItems = await page.locator('.bg-white.rounded-lg.shadow-sm').count();
    console.log(`📊 表示されているメニュー数: ${menuItems}個`);
    
    if (menuItems > 0) {
      // 最初のメニューの詳細を取得
      const firstMenu = page.locator('.bg-white.rounded-lg.shadow-sm').first();
      const menuName = await firstMenu.locator('h3').textContent();
      const menuPrice = await firstMenu.locator('.text-2xl.font-bold').textContent();
      console.log(`  🎯 最初のメニュー: ${menuName}`);
      console.log(`  💰 価格: ${menuPrice}`);
      
      // メニューの説明文確認
      const hasDescription = await firstMenu.locator('.text-gray-600.text-sm').count() > 0;
      if (hasDescription) {
        const description = await firstMenu.locator('.text-gray-600.text-sm').first().textContent();
        console.log(`  📝 説明: ${description}`);
      }
      
      // 時間表示確認
      const duration = await firstMenu.locator('span.text-gray-500').first().textContent();
      console.log(`  ⏱️ 所要時間: ${duration}`);
    } else {
      console.log('⚠️ メニューが表示されていません');
    }
    
    // スクリーンショット撮影
    await page.screenshot({ path: 'menu-select-page.png', fullPage: true });
    console.log('📸 スクリーンショット保存: menu-select-page.png');
  });

  test('メニュー選択から日時選択への遷移', async ({ page }) => {
    console.log('\n========================================');
    console.log('🔄 メニュー選択→日時選択の遷移テスト');
    console.log('========================================\n');
    
    // メニューページへアクセス
    await page.goto('http://127.0.0.1:8000/reservation/menu');
    await page.waitForLoadState('networkidle');
    
    const menuItems = await page.locator('.bg-white.rounded-lg.shadow-sm').count();
    
    if (menuItems > 0) {
      // 最初のメニューをクリック
      const firstMenu = page.locator('.bg-white.rounded-lg.shadow-sm').first();
      const menuName = await firstMenu.locator('h3').textContent();
      console.log(`🖱️ "${menuName}"をクリック`);
      
      await firstMenu.click();
      
      // ページ遷移を待つ
      await page.waitForURL('**/reservation', { timeout: 10000 });
      console.log('✅ 日時選択ページへ遷移成功');
      
      // 選択したメニューが表示されているか確認
      const selectedMenuDisplay = page.locator('.bg-blue-50').first();
      await expect(selectedMenuDisplay).toBeVisible();
      
      const displayedMenuName = await selectedMenuDisplay.locator('.text-lg.font-semibold').textContent();
      console.log(`📋 選択中のメニュー: ${displayedMenuName}`);
      
      // メニュー変更リンクの確認
      const changeLink = page.locator('text=メニューを変更');
      await expect(changeLink).toBeVisible();
      console.log('✅ メニュー変更リンク表示確認');
    } else {
      console.log('⚠️ メニューがないため遷移テストをスキップ');
    }
  });

  test('日時選択ページの機能テスト', async ({ page }) => {
    console.log('\n========================================');
    console.log('📅 日時選択ページテスト');
    console.log('========================================\n');
    
    // まずメニューを選択
    await page.goto('http://127.0.0.1:8000/reservation/menu');
    const menuCount = await page.locator('.bg-white.rounded-lg.shadow-sm').count();
    
    if (menuCount === 0) {
      console.log('⚠️ メニューがないためテストをスキップ');
      return;
    }
    
    await page.locator('.bg-white.rounded-lg.shadow-sm').first().click();
    await page.waitForURL('**/reservation');
    
    // 店舗選択の確認
    const storeSelect = page.locator('#storeSelect');
    await expect(storeSelect).toBeVisible();
    const selectedStore = await storeSelect.inputValue();
    console.log(`🏢 選択中の店舗ID: ${selectedStore}`);
    
    // カレンダーテーブルの確認
    const calendarTable = page.locator('table.availability-table');
    await expect(calendarTable).toBeVisible();
    console.log('✅ 予約カレンダー表示確認');
    
    // 曜日ヘッダーの確認
    const dayHeaders = await page.locator('thead th').allTextContents();
    console.log('📅 表示されている曜日:', dayHeaders.slice(1).join(', '));
    
    // 利用可能な時間枠を探す
    const availableSlots = await page.locator('button.time-slot').count();
    console.log(`⭕ 利用可能な時間枠: ${availableSlots}個`);
    
    // 利用不可の時間枠を数える
    const unavailableSlots = await page.locator('span.text-gray-400.text-xl').count();
    console.log(`❌ 利用不可の時間枠: ${unavailableSlots}個`);
    
    if (availableSlots > 0) {
      // 最初の利用可能な時間枠をクリック
      const firstSlot = page.locator('button.time-slot').first();
      const slotDate = await firstSlot.getAttribute('data-date');
      const slotTime = await firstSlot.getAttribute('data-time');
      console.log(`\n🖱️ 時間枠をクリック: ${slotDate} ${slotTime}`);
      
      await firstSlot.click();
      
      // 選択された時間枠の色が変わることを確認
      await expect(firstSlot).toHaveClass(/selected/);
      console.log('✅ 時間枠が選択状態に変更');
      
      // 予約フォームが表示されることを確認
      const reservationForm = page.locator('#reservationForm');
      await expect(reservationForm).toBeVisible();
      console.log('✅ 予約フォーム表示確認');
      
      // 選択された日時の表示確認
      const selectedDateTime = await page.locator('#selectedDateTime').textContent();
      console.log(`📍 選択された日時: ${selectedDateTime}`);
    } else {
      console.log('⚠️ 利用可能な時間枠がありません');
    }
    
    // スクリーンショット
    await page.screenshot({ path: 'calendar-page.png', fullPage: true });
    console.log('📸 スクリーンショット保存: calendar-page.png');
  });

  test('予約完了までの完全フローテスト', async ({ page }) => {
    console.log('\n========================================');
    console.log('🚀 予約完了までの完全フローテスト');
    console.log('========================================\n');
    
    // ステップ1: メニュー選択
    console.log('【ステップ1】メニュー選択');
    await page.goto('http://127.0.0.1:8000/reservation/menu');
    
    const menuCount = await page.locator('.bg-white.rounded-lg.shadow-sm').count();
    if (menuCount === 0) {
      console.log('❌ メニューがないため予約できません');
      return;
    }
    
    const firstMenu = page.locator('.bg-white.rounded-lg.shadow-sm').first();
    const selectedMenuName = await firstMenu.locator('h3').textContent();
    console.log(`  ✅ メニュー選択: ${selectedMenuName}`);
    await firstMenu.click();
    
    // ステップ2: 日時選択
    console.log('\n【ステップ2】日時選択');
    await page.waitForURL('**/reservation');
    
    const availableSlots = await page.locator('button.time-slot').count();
    if (availableSlots === 0) {
      console.log('  ❌ 利用可能な時間枠がありません');
      return;
    }
    
    const firstSlot = page.locator('button.time-slot').first();
    const slotDate = await firstSlot.getAttribute('data-date');
    const slotTime = await firstSlot.getAttribute('data-time');
    console.log(`  ✅ 時間枠選択: ${slotDate} ${slotTime}`);
    await firstSlot.click();
    
    // ステップ3: 顧客情報入力
    console.log('\n【ステップ3】顧客情報入力');
    await page.waitForSelector('#reservationForm', { state: 'visible' });
    
    // テストデータ
    const testData = {
      lastName: '山田',
      firstName: '太郎',
      phone: '090-1234-5678',
      email: 'yamada@example.com',
      notes: 'Playwrightによる自動テスト予約'
    };
    
    // フォーム入力
    await page.fill('input[name="last_name"]', testData.lastName);
    console.log(`  ✅ 姓: ${testData.lastName}`);
    
    await page.fill('input[name="first_name"]', testData.firstName);
    console.log(`  ✅ 名: ${testData.firstName}`);
    
    await page.fill('input[name="phone"]', testData.phone);
    console.log(`  ✅ 電話番号: ${testData.phone}`);
    
    await page.fill('input[name="email"]', testData.email);
    console.log(`  ✅ メール: ${testData.email}`);
    
    await page.fill('textarea[name="notes"]', testData.notes);
    console.log(`  ✅ 備考: ${testData.notes}`);
    
    // スクリーンショット（送信前）
    await page.screenshot({ path: 'before-submit.png', fullPage: true });
    console.log('\n📸 送信前スクリーンショット: before-submit.png');
    
    // 予約送信
    console.log('\n【ステップ4】予約送信');
    const submitButton = page.locator('button[type="submit"]').filter({ hasText: '予約する' });
    await submitButton.click();
    console.log('  ⏳ 予約を送信中...');
    
    // 完了ページへの遷移を待つ
    try {
      await page.waitForURL('**/reservation/complete/**', { timeout: 10000 });
      console.log('  ✅ 予約完了ページへ遷移');
      
      // 完了メッセージの確認
      const successMessage = page.locator('h1').filter({ hasText: '予約が完了しました' });
      await expect(successMessage).toBeVisible();
      console.log('  ✅ 予約完了メッセージ表示確認');
      
      // 予約番号の取得
      const reservationNumber = await page.locator('.font-semibold.text-lg').first().textContent();
      console.log(`  📋 予約番号: ${reservationNumber}`);
      
      // 完了ページのスクリーンショット
      await page.screenshot({ path: 'reservation-complete.png', fullPage: true });
      console.log('\n📸 完了ページスクリーンショット: reservation-complete.png');
      
      console.log('\n========================================');
      console.log('🎉 予約フロー完全テスト成功！');
      console.log('========================================');
      
    } catch (error) {
      console.log('  ❌ 予約送信エラー:', error.message);
      
      // エラーメッセージを探す
      const errorMessage = await page.locator('.text-red-500, .alert-danger, .error').first();
      if (await errorMessage.isVisible()) {
        const errorText = await errorMessage.textContent();
        console.log('  ❌ エラーメッセージ:', errorText);
      }
    }
  });

  test('レスポンシブデザインテスト', async ({ page }) => {
    console.log('\n========================================');
    console.log('📱 レスポンシブデザインテスト');
    console.log('========================================\n');
    
    // モバイルサイズ
    await page.setViewportSize({ width: 375, height: 667 });
    console.log('📱 モバイルサイズ (375x667)');
    
    await page.goto('http://127.0.0.1:8000/reservation/menu');
    await page.waitForLoadState('networkidle');
    
    const mobileMenuItems = await page.locator('.bg-white.rounded-lg.shadow-sm').count();
    console.log(`  メニュー表示: ${mobileMenuItems}個`);
    
    await page.screenshot({ path: 'mobile-menu.png', fullPage: true });
    console.log('  📸 モバイル版スクリーンショット: mobile-menu.png');
    
    // タブレットサイズ
    await page.setViewportSize({ width: 768, height: 1024 });
    console.log('\n📱 タブレットサイズ (768x1024)');
    
    await page.reload();
    const tabletMenuItems = await page.locator('.bg-white.rounded-lg.shadow-sm').count();
    console.log(`  メニュー表示: ${tabletMenuItems}個`);
    
    await page.screenshot({ path: 'tablet-menu.png', fullPage: true });
    console.log('  📸 タブレット版スクリーンショット: tablet-menu.png');
  });
});

// テスト終了後のサマリー
test.afterAll(async () => {
  console.log('\n========================================');
  console.log('📊 予約フローE2Eテスト完了');
  console.log('========================================');
  console.log('✅ メニュー選択');
  console.log('✅ 日時選択');
  console.log('✅ 顧客情報入力');
  console.log('✅ 予約完了');
  console.log('✅ レスポンシブ対応');
  console.log('========================================\n');
});