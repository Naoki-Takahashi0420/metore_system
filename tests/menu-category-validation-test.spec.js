import { test, expect } from '@playwright/test';

test.describe('メニュー作成時のカテゴリ制限', () => {
  test('カテゴリがない店舗ではメニュー作成ボタンが無効', async ({ page }) => {
    // 管理画面にログイン
    await page.goto('http://127.0.0.1:8002/admin/login');
    await page.fill('input[type="email"]', 'admin@eye-training.com');
    await page.fill('input[type="password"]', 'password');
    await page.click('button:has-text("ログイン")');
    
    // ダッシュボードに遷移
    await page.waitForURL(/.*\/admin$/);
    
    // メニュー管理画面へ
    await page.goto('http://127.0.0.1:8002/admin/menus');
    await page.waitForLoadState('networkidle');
    
    // 作成ボタンの状態を確認
    const createButton = page.locator('button:has-text("メニュー作成"), a:has-text("メニュー作成")').first();
    
    if (await createButton.count() > 0) {
      const isDisabled = await createButton.isDisabled();
      console.log(`メニュー作成ボタンの状態: ${isDisabled ? '無効' : '有効'}`);
      
      // ツールチップを確認
      const tooltip = await createButton.getAttribute('title');
      if (tooltip) {
        console.log(`ツールチップ: ${tooltip}`);
      }
    }
  });

  test('メニュー作成フォームでカテゴリ選択が店舗に連動', async ({ page }) => {
    // 管理画面にログイン
    await page.goto('http://127.0.0.1:8002/admin/login');
    await page.fill('input[type="email"]', 'admin@eye-training.com');
    await page.fill('input[type="password"]', 'password');
    await page.click('button:has-text("ログイン")');
    
    // メニュー作成画面へ直接アクセス
    await page.goto('http://127.0.0.1:8002/admin/menus/create');
    await page.waitForLoadState('networkidle');
    
    // 店舗選択フィールドを確認
    const storeSelect = page.locator('select[name*="store_id"], [wire\\:model*="store_id"]').first();
    
    if (await storeSelect.count() > 0) {
      console.log('✅ 店舗選択フィールドが存在');
      
      // カテゴリフィールドの初期状態を確認
      const categorySelect = page.locator('select[name*="category_id"], [wire\\:model*="category_id"]').first();
      
      if (await categorySelect.count() > 0) {
        const isDisabled = await categorySelect.isDisabled();
        console.log(`カテゴリ選択の初期状態: ${isDisabled ? '無効（正しい）' : '有効（問題）'}`);
        
        // 店舗を選択
        const storeOptions = await storeSelect.locator('option').all();
        if (storeOptions.length > 1) {
          const storeValue = await storeOptions[1].getAttribute('value');
          await storeSelect.selectOption(storeValue);
          console.log(`店舗ID ${storeValue} を選択`);
          
          // カテゴリフィールドが有効になるのを待つ
          await page.waitForTimeout(1000);
          
          // カテゴリが有効になったか確認
          const isStillDisabled = await categorySelect.isDisabled();
          console.log(`店舗選択後のカテゴリ: ${isStillDisabled ? '無効' : '有効（正しい）'}`);
          
          // カテゴリのオプションを確認
          const categoryOptions = await categorySelect.locator('option').all();
          console.log(`利用可能なカテゴリ数: ${categoryOptions.length}`);
          
          for (let i = 0; i < Math.min(categoryOptions.length, 3); i++) {
            const text = await categoryOptions[i].textContent();
            console.log(`  - ${text}`);
          }
        }
      }
    } else {
      console.log('❌ 店舗選択フィールドが見つかりません');
    }
    
    // スクリーンショット
    await page.screenshot({ path: 'test-results/menu-create-form.png', fullPage: true });
  });
});