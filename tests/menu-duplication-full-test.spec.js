import { test, expect } from '@playwright/test';

test('カテゴリ複製機能の完全テスト', async ({ page }) => {
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
  
  // 複製アクションを実行
  const duplicateButton = page.locator('button[title*="複製"]').first();
  
  if (await duplicateButton.count() > 0) {
    console.log('複製ボタンをクリック');
    await duplicateButton.click();
    
    // モーダルが開くのを待つ
    await page.waitForTimeout(1000);
    
    // 店舗選択
    const targetStoreSelect = page.locator('select[wire\\:model*="target_store"], select[name*="target_store"]').first();
    
    if (await targetStoreSelect.count() > 0) {
      console.log('店舗選択フィールドが見つかりました');
      
      // 選択可能なオプションを取得
      const options = await targetStoreSelect.locator('option').all();
      console.log(`利用可能な店舗数: ${options.length}`);
      
      if (options.length > 1) {
        // 2番目の店舗を選択（最初は「選択してください」の可能性）
        const optionValue = await options[1].getAttribute('value');
        await targetStoreSelect.selectOption(optionValue);
        console.log(`店舗 ${optionValue} を選択`);
        
        // 送信ボタンを探してクリック
        const submitButton = page.locator('button[type="submit"], button:has-text("複製"), button:has-text("実行")').last();
        if (await submitButton.count() > 0) {
          console.log('複製実行ボタンをクリック');
          await submitButton.click();
          
          // 成功メッセージを待つ
          try {
            await expect(page.locator('text=複製完了')).toBeVisible({ timeout: 10000 });
            console.log('✅ 複製が成功しました！');
          } catch (error) {
            // エラーメッセージをチェック
            const errorMessage = await page.locator('text=複製失敗, text=エラー').first();
            if (await errorMessage.count() > 0) {
              const errorText = await errorMessage.textContent();
              console.log(`❌ エラー: ${errorText}`);
            }
          }
        }
      }
    } else {
      console.log('店舗選択フィールドが見つかりません');
    }
  } else {
    console.log('複製ボタンが見つかりません');
  }
  
  // 結果のスクリーンショット
  await page.screenshot({ path: 'test-results/menu-duplication/final-result.png' });
});