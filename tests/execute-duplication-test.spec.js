import { test, expect } from '@playwright/test';

test('カテゴリ複製を実行', async ({ page }) => {
  // 管理画面にログイン
  await page.goto('http://127.0.0.1:8002/admin/login');
  await page.fill('input[type="email"]', 'admin@eye-training.com');
  await page.fill('input[type="password"]', 'password');
  await page.click('button:has-text("ログイン")');
  
  // ダッシュボードに遷移を確認
  await page.waitForURL(/.*\/admin$/);
  
  // メニューカテゴリー画面へ
  await page.goto('http://127.0.0.1:8002/admin/menu-categories');
  await page.waitForLoadState('networkidle');
  
  // 最初の複製ボタンをクリック
  const firstDuplicateButton = page.locator('button:has-text("他店舗へ複製")').first();
  
  if (await firstDuplicateButton.count() > 0) {
    console.log('✅ 複製ボタンが見つかりました');
    await firstDuplicateButton.click();
    
    // モーダルが開くのを待つ
    await page.waitForTimeout(1000);
    
    // モーダル内の店舗選択フィールドを探す
    const modal = page.locator('[role="dialog"], .filament-modal, .fi-modal');
    
    if (await modal.count() > 0) {
      console.log('✅ モーダルが開きました');
      
      // 店舗選択フィールド
      const storeSelect = modal.locator('select').first();
      
      if (await storeSelect.count() > 0) {
        const options = await storeSelect.locator('option').all();
        console.log(`利用可能な店舗数: ${options.length}`);
        
        if (options.length > 1) {
          // 2番目のオプションを選択
          const value = await options[1].getAttribute('value');
          await storeSelect.selectOption(value);
          console.log(`店舗ID ${value} を選択`);
          
          // 実行ボタンをクリック
          const submitButton = modal.locator('button[type="submit"], button:has-text("実行"), button:has-text("複製")').last();
          await submitButton.click();
          console.log('複製を実行中...');
          
          // 結果を待つ
          await page.waitForTimeout(2000);
          
          // 成功またはエラーメッセージを確認
          const successMessage = page.locator('[role="status"]:has-text("複製完了"), .filament-notification:has-text("複製完了")');
          const errorMessage = page.locator('[role="alert"]:has-text("複製失敗"), .filament-notification:has-text("複製失敗"), .filament-notification:has-text("エラー")');
          
          if (await successMessage.count() > 0) {
            console.log('✅ 複製が成功しました！');
            const message = await successMessage.textContent();
            console.log(`メッセージ: ${message}`);
          } else if (await errorMessage.count() > 0) {
            console.log('❌ 複製に失敗しました');
            const message = await errorMessage.textContent();
            console.log(`エラー: ${message}`);
          } else {
            console.log('⚠️ 成功/エラーメッセージが見つかりません');
          }
        }
      } else {
        console.log('❌ 店舗選択フィールドが見つかりません');
      }
    } else {
      console.log('❌ モーダルが開きません');
    }
  } else {
    console.log('❌ 複製ボタンが見つかりません');
  }
  
  // 最終スクリーンショット
  await page.screenshot({ path: 'test-results/duplication-result.png', fullPage: true });
});