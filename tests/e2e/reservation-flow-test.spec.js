import { test, expect } from '@playwright/test';

test.describe('予約フロー完全テスト', () => {
  
  test('コース選定→時間指定→日付指定の完全フロー', async ({ page }) => {
    // 1. 店舗一覧から開始
    await page.goto('/stores');
    await expect(page).toHaveTitle(/店舗/);
    
    // 店舗選択ボタンが表示されることを確認
    const selectStoreButton = page.locator('button').filter({ hasText: 'この店舗を選択' }).first();
    await expect(selectStoreButton).toBeVisible();
    
    // 店舗を選択
    await selectStoreButton.click();
    
    // 2. カテゴリー選択画面へ遷移
    await page.waitForURL('**/reservation/category');
    await expect(page.locator('h1')).toContainText('カテゴリーを選択');
    
    // カテゴリーが表示されることを確認
    const categoryCards = page.locator('.bg-white.rounded-lg.shadow-md');
    const categoryCount = await categoryCards.count();
    console.log(`カテゴリー数: ${categoryCount}`);
    expect(categoryCount).toBeGreaterThan(0);
    
    // 最初のカテゴリーを選択
    await categoryCards.first().click();
    
    // 3. 時間選択画面へ遷移
    await page.waitForURL('**/reservation/time');
    await expect(page.locator('h1')).toContainText('時間を選択');
    
    // メニューカードが表示されることを確認
    const menuCards = page.locator('.menu-card');
    const menuCount = await menuCards.count();
    console.log(`メニュー数: ${menuCount}`);
    expect(menuCount).toBeGreaterThan(0);
    
    // 最初のメニューの時間を選択
    const firstMenuCard = menuCards.first();
    const timeButton = firstMenuCard.locator('button').filter({ hasText: /\d{1,2}:\d{2}/ }).first();
    await expect(timeButton).toBeVisible();
    await timeButton.click();
    
    // 4. カレンダー画面へ遷移
    await page.waitForURL('**/reservation/calendar');
    await expect(page.locator('h1')).toContainText('日付を選択');
    
    // カレンダーが表示されることを確認
    const calendar = page.locator('.calendar-container, table').first();
    await expect(calendar).toBeVisible();
    
    // 予約可能な日付（○マーク）を探す
    const availableDates = page.locator('td').filter({ hasText: '○' });
    const availableCount = await availableDates.count();
    console.log(`予約可能日数: ${availableCount}`);
    
    if (availableCount > 0) {
      // 最初の予約可能日を選択
      await availableDates.first().click();
      
      // 予約フォームが表示されることを確認
      await expect(page.locator('input[name="customer_name"]')).toBeVisible();
      
      // 顧客情報を入力
      await page.fill('input[name="customer_name"]', 'テスト太郎');
      await page.fill('input[name="customer_phone"]', '09012345678');
      await page.fill('input[name="customer_email"]', 'test@example.com');
      
      // 予約を確定
      const submitButton = page.locator('button[type="submit"]').filter({ hasText: '予約を確定' });
      await submitButton.click();
      
      // 完了画面へ遷移
      await page.waitForURL('**/reservation/complete/**');
      await expect(page.locator('text=予約が完了しました')).toBeVisible();
    } else {
      console.log('予約可能な日付がありません');
    }
  });
  
  test('各ステップでの戻るボタン動作確認', async ({ page }) => {
    // 店舗選択から開始
    await page.goto('/stores');
    const selectStoreButton = page.locator('button').filter({ hasText: 'この店舗を選択' }).first();
    await selectStoreButton.click();
    
    // カテゴリー選択画面
    await page.waitForURL('**/reservation/category');
    const categoryCards = page.locator('.bg-white.rounded-lg.shadow-md');
    await categoryCards.first().click();
    
    // 時間選択画面で戻るボタンをテスト
    await page.waitForURL('**/reservation/time');
    const backButton = page.locator('button, a').filter({ hasText: '戻る' });
    if (await backButton.count() > 0) {
      await backButton.click();
      await expect(page).toHaveURL(/reservation\/category/);
      console.log('時間選択画面から戻る: OK');
      
      // 再度進む
      await categoryCards.first().click();
      await page.waitForURL('**/reservation/time');
    }
    
    // メニューを選択して次へ
    const timeButton = page.locator('button').filter({ hasText: /\d{1,2}:\d{2}/ }).first();
    await timeButton.click();
    
    // カレンダー画面で戻るボタンをテスト
    await page.waitForURL('**/reservation/calendar');
    const calendarBackButton = page.locator('button, a').filter({ hasText: '戻る' });
    if (await calendarBackButton.count() > 0) {
      await calendarBackButton.click();
      await expect(page).toHaveURL(/reservation\/time/);
      console.log('カレンダー画面から戻る: OK');
    }
  });
  
  test('モバイル表示での予約フロー', async ({ page }) => {
    // iPhone 12のビューポートに設定
    await page.setViewportSize({ width: 390, height: 844 });
    
    // 店舗一覧
    await page.goto('/stores');
    const selectStoreButton = page.locator('button').filter({ hasText: 'この店舗を選択' }).first();
    await selectStoreButton.click();
    
    // カテゴリー選択
    await page.waitForURL('**/reservation/category');
    
    // モバイルでカテゴリーカードが適切に表示されることを確認
    const categoryCards = page.locator('.bg-white.rounded-lg.shadow-md');
    const firstCard = await categoryCards.first().boundingBox();
    if (firstCard) {
      // モバイルでカードが画面幅に収まっていることを確認
      expect(firstCard.width).toBeLessThanOrEqual(390 - 32); // 画面幅 - パディング
    }
    
    await categoryCards.first().click();
    
    // 時間選択画面
    await page.waitForURL('**/reservation/time');
    
    // モバイルでステップインジケーターが表示されることを確認
    const stepIndicator = page.locator('.flex.items-center.justify-center');
    await expect(stepIndicator).toBeVisible();
    
    // 時間ボタンがタップ可能なサイズであることを確認
    const timeButton = page.locator('button').filter({ hasText: /\d{1,2}:\d{2}/ }).first();
    const buttonBox = await timeButton.boundingBox();
    if (buttonBox) {
      expect(buttonBox.height).toBeGreaterThanOrEqual(44); // iOS推奨タップサイズ
    }
    
    await timeButton.click();
    
    // カレンダー画面
    await page.waitForURL('**/reservation/calendar');
    
    // モバイルでカレンダーが適切に表示されることを確認
    const calendar = page.locator('.calendar-container, table').first();
    await expect(calendar).toBeVisible();
    const calendarBox = await calendar.boundingBox();
    if (calendarBox) {
      expect(calendarBox.width).toBeLessThanOrEqual(390);
    }
  });
  
  test('セッションデータの保持確認', async ({ page }) => {
    // 店舗選択
    await page.goto('/stores');
    const selectStoreButton = page.locator('button').filter({ hasText: 'この店舗を選択' }).first();
    await selectStoreButton.click();
    
    // カテゴリー選択
    await page.waitForURL('**/reservation/category');
    const categoryCards = page.locator('.bg-white.rounded-lg.shadow-md');
    await categoryCards.first().click();
    
    // 時間選択
    await page.waitForURL('**/reservation/time');
    
    // セッションストレージを確認
    const sessionData = await page.evaluate(() => {
      return {
        store: sessionStorage.getItem('selected_store'),
        category: sessionStorage.getItem('selected_category'),
        menu: sessionStorage.getItem('selected_menu')
      };
    });
    
    console.log('セッションデータ:', sessionData);
    expect(sessionData.store).toBeTruthy();
    expect(sessionData.category).toBeTruthy();
    
    // メニューを選択
    const timeButton = page.locator('button').filter({ hasText: /\d{1,2}:\d{2}/ }).first();
    await timeButton.click();
    
    // カレンダー画面でセッションを再確認
    await page.waitForURL('**/reservation/calendar');
    const updatedSessionData = await page.evaluate(() => {
      return {
        store: sessionStorage.getItem('selected_store'),
        category: sessionStorage.getItem('selected_category'),
        menu: sessionStorage.getItem('selected_menu'),
        time: sessionStorage.getItem('selected_time')
      };
    });
    
    console.log('更新後のセッションデータ:', updatedSessionData);
    expect(updatedSessionData.menu).toBeTruthy();
    expect(updatedSessionData.time).toBeTruthy();
  });
  
  test('エラーハンドリング - 無効な入力', async ({ page }) => {
    // 直接カレンダーページにアクセス（セッションなし）
    await page.goto('/reservation/calendar');
    
    // エラーメッセージまたはリダイレクトを確認
    const currentUrl = page.url();
    if (currentUrl.includes('/reservation/calendar')) {
      // エラーメッセージが表示されるか確認
      const errorMessage = page.locator('.alert-danger, .error-message, text=エラー');
      if (await errorMessage.count() > 0) {
        console.log('エラーメッセージ表示: OK');
      }
    } else {
      // リダイレクトされた場合
      console.log('適切にリダイレクト: OK');
      expect(currentUrl).toMatch(/stores|reservation\/store/);
    }
  });
});