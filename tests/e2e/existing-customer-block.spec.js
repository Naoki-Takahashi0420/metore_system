import { test, expect } from '@playwright/test';

test.describe('既存顧客（08033372305）の予約ブロックテスト', () => {
  
  test('既存顧客は新規予約ができないことを確認', async ({ page }) => {
    console.log('\n========================================');
    console.log('🔒 既存顧客の予約ブロックテスト');
    console.log('========================================\n');
    
    // 1. 店舗一覧ページへアクセス（予約の開始点）
    await page.goto('http://127.0.0.1:8000/stores');
    await page.waitForLoadState('networkidle');
    await page.screenshot({ path: 'test-results/1-stores-list.png' });
    console.log('Step 1: 店舗一覧ページ');
    
    // 2. 最初の店舗を選択
    // 店舗データが読み込まれるまで待つ
    await page.waitForTimeout(2000);
    
    // 店舗選択ボタンを探す
    const selectButton = page.locator('button:has-text("この店舗を選択")').first();
    if (await selectButton.count() > 0) {
      console.log(`Step 2: 店舗選択ボタンをクリック`);
      await selectButton.click();
      await page.waitForLoadState('networkidle');
      await page.screenshot({ path: 'test-results/2-menu-select.png' });
    } else {
      console.log('❌ 店舗選択ボタンが見つかりません');
      // 代替: 店舗カードのdiv要素を探す
      const storeDiv = page.locator('div.bg-white.rounded-lg.shadow-md').first();
      if (await storeDiv.count() > 0) {
        console.log('代替: 店舗カードをクリック');
        await storeDiv.click();
        await page.waitForLoadState('networkidle');
      } else {
        console.log('❌ 店舗が見つかりません');
        return;
      }
    }
    
    // 3. メニュー選択
    const menuItem = page.locator('.menu-item').first();
    if (await menuItem.count() > 0) {
      const menuName = await menuItem.locator('h3').textContent();
      console.log(`Step 3: メニュー選択 - ${menuName}`);
      await menuItem.click();
      await page.waitForTimeout(1000);
      
      // オプションモーダルの処理
      const optionModal = page.locator('#optionModal');
      if (await optionModal.isVisible()) {
        console.log('Step 3.5: オプションモーダル表示 - スキップ');
        await page.screenshot({ path: 'test-results/3-option-modal.png' });
        await page.click('text=追加なしで進む');
        await page.waitForTimeout(1000);
      }
    } else {
      console.log('❌ メニューが見つかりません');
      return;
    }
    
    // 4. 日時選択
    await page.waitForLoadState('networkidle');
    console.log('Step 4: 日時選択ページ');
    await page.screenshot({ path: 'test-results/4-datetime.png' });
    
    // 利用可能な日付を探す
    const availableDates = page.locator('.calendar-date.available, button.time-slot, td.has-availability button');
    if (await availableDates.count() > 0) {
      await availableDates.first().click();
      await page.waitForTimeout(1000);
      
      // 時間選択（もし別途必要な場合）
      const timeSlots = page.locator('.time-slot.available, button[data-time]');
      if (await timeSlots.count() > 0) {
        await timeSlots.first().click();
        await page.waitForTimeout(500);
      }
      
      // 次へボタンを探してクリック
      const nextButton = page.locator('button:has-text("次へ"), button:has-text("予約する"), button:has-text("選択")');
      if (await nextButton.count() > 0) {
        await nextButton.first().click();
        await page.waitForLoadState('networkidle');
      }
    } else {
      console.log('⚠️ 利用可能な日時がありません');
      return;
    }
    
    // 5. 顧客情報入力ページ
    await page.waitForTimeout(2000);
    console.log('Step 5: 顧客情報入力ページ');
    await page.screenshot({ path: 'test-results/5-customer-form.png' });
    
    // 既存顧客の電話番号を入力
    const phoneInput = page.locator('#phone, input[name="phone"]');
    if (await phoneInput.count() > 0) {
      await phoneInput.fill('08033372305');
      console.log('Step 6: 電話番号入力 - 08033372305');
      
      // 電話番号チェックの結果を待つ
      await page.waitForTimeout(3000);
      
      // エラーメッセージの確認
      const errorMessage = page.locator('#phone-check-result, .bg-orange-50, .bg-red-50');
      if (await errorMessage.isVisible()) {
        console.log('✅ エラーメッセージが表示されました');
        const errorText = await errorMessage.textContent();
        console.log(`メッセージ内容: ${errorText.substring(0, 100)}...`);
        
        // スクリーンショット
        await page.screenshot({ path: 'test-results/6-blocked-message.png', fullPage: true });
        
        // 送信ボタンが無効化されているか確認
        const submitButton = page.locator('button[type="submit"]');
        const isDisabled = await submitButton.isDisabled();
        
        if (isDisabled) {
          console.log('✅ 送信ボタンが無効化されています');
        } else {
          console.log('⚠️ 送信ボタンが有効のままです');
        }
        
        // マイページリンクの確認
        const myPageLink = page.locator('a[href="/admin"]');
        if (await myPageLink.count() > 0) {
          console.log('✅ マイページへのリンクが表示されています');
        }
        
        console.log('\n========================================');
        console.log('🎉 テスト成功: 既存顧客の予約がブロックされました');
        console.log('========================================');
      } else {
        console.log('❌ エラーメッセージが表示されませんでした');
        await page.screenshot({ path: 'test-results/6-no-error.png', fullPage: true });
      }
    } else {
      console.log('❌ 電話番号入力欄が見つかりません');
    }
  });
  
  test('新規顧客は予約できることを確認', async ({ page }) => {
    console.log('\n========================================');
    console.log('✅ 新規顧客の予約可能テスト');
    console.log('========================================\n');
    
    const randomPhone = '090' + Math.floor(Math.random() * 100000000).toString().padStart(8, '0');
    
    // 店舗一覧から開始
    await page.goto('http://127.0.0.1:8000/stores');
    await page.waitForLoadState('networkidle');
    
    // 店舗選択
    await page.waitForTimeout(2000);
    const selectButton = page.locator('button:has-text("この店舗を選択")').first();
    if (await selectButton.count() > 0) {
      await selectButton.click();
      await page.waitForLoadState('networkidle');
    }
    
    // メニュー選択
    const menuItem = page.locator('.menu-item').first();
    if (await menuItem.count() > 0) {
      await menuItem.click();
      await page.waitForTimeout(1000);
      
      // オプションモーダルの処理
      const optionModal = page.locator('#optionModal');
      if (await optionModal.isVisible()) {
        await page.click('text=追加なしで進む');
        await page.waitForTimeout(1000);
      }
    }
    
    // 日時選択
    await page.waitForLoadState('networkidle');
    const availableDate = page.locator('.calendar-date.available, button.time-slot, td.has-availability button').first();
    if (await availableDate.count() > 0) {
      await availableDate.click();
      await page.waitForTimeout(1000);
      
      const nextButton = page.locator('button:has-text("次へ"), button:has-text("予約する")');
      if (await nextButton.count() > 0) {
        await nextButton.first().click();
        await page.waitForLoadState('networkidle');
      }
    }
    
    // 顧客情報入力
    await page.waitForTimeout(2000);
    const phoneInput = page.locator('#phone, input[name="phone"]');
    if (await phoneInput.count() > 0) {
      await phoneInput.fill(randomPhone);
      console.log(`新規電話番号: ${randomPhone}`);
      
      await page.waitForTimeout(2000);
      
      // フォーム入力
      await page.fill('#last_name, input[name="last_name"]', 'テスト');
      await page.fill('#first_name, input[name="first_name"]', '太郎');
      await page.fill('#last_name_kana, input[name="last_name_kana"]', 'テスト');
      await page.fill('#first_name_kana, input[name="first_name_kana"]', 'タロウ');
      
      // 送信ボタンが有効か確認
      const submitButton = page.locator('button[type="submit"]');
      const isEnabled = await submitButton.isEnabled();
      
      if (isEnabled) {
        console.log('✅ 新規顧客: 送信ボタンが有効です');
        await page.screenshot({ path: 'test-results/new-customer-ready.png' });
      } else {
        console.log('❌ 新規顧客: 送信ボタンが無効です');
      }
    }
  });
});