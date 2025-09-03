import { test, expect } from '@playwright/test';

test.describe('メニューカテゴリ複製機能', () => {
  test('カテゴリを他店舗へ複製できる', async ({ page }) => {
    // 管理画面にログイン
    await page.goto('http://127.0.0.1:8002/admin/login');
    await page.fill('input[name="email"]', 'admin@eye-training.com');
    await page.fill('input[name="password"]', 'password');
    await page.click('button[type="submit"]');
    
    // ログイン成功を確認
    await expect(page).toHaveURL(/.*\/admin/);
    
    // メニューカテゴリー画面へ移動
    await page.goto('http://127.0.0.1:8002/admin/menu-categories');
    await page.waitForLoadState('networkidle');
    
    // カテゴリ一覧が表示されているか確認
    await expect(page.locator('table')).toBeVisible();
    
    // 最初のカテゴリの複製ボタンをクリック（緑の複製アイコン）
    const duplicateButton = page.locator('button[wire\\:click*="duplicate"]').first();
    
    if (await duplicateButton.count() > 0) {
      await duplicateButton.click();
      
      // モーダルまたはドロップダウンが表示されるのを待つ
      await page.waitForTimeout(1000);
      
      // 店舗選択フィールドが表示されているか確認
      const storeSelect = page.locator('select[name*="target_store_id"], select[wire\\:model*="target_store_id"]');
      
      if (await storeSelect.count() > 0) {
        // 店舗を選択
        const options = await storeSelect.locator('option').all();
        if (options.length > 1) {
          await storeSelect.selectOption({ index: 1 });
        }
        
        // 複製実行ボタンをクリック
        await page.click('button:has-text("複製"), button:has-text("実行")');
        
        // 成功メッセージを確認
        await expect(page.locator('text=複製完了')).toBeVisible({ timeout: 5000 });
      }
    }
  });

  test('メニュー統合管理での複製機能', async ({ page }) => {
    // 管理画面にログイン
    await page.goto('http://127.0.0.1:8002/admin/login');
    await page.fill('input[name="email"]', 'admin@eye-training.com');
    await page.fill('input[name="password"]', 'password');
    await page.click('button[type="submit"]');
    
    // メニュー統合管理へ移動
    await page.goto('http://127.0.0.1:8002/admin/menu-manager');
    await page.waitForLoadState('networkidle');
    
    // カテゴリが表示されているか確認
    await expect(page.locator('text=カテゴリー')).toBeVisible({ timeout: 10000 });
    
    // 複製ボタンが存在するか確認
    const duplicateIcon = page.locator('[title="他店舗へ複製"]').first();
    
    if (await duplicateIcon.count() > 0) {
      await duplicateIcon.click();
      
      // ドロップダウンメニューが表示されるのを待つ
      await page.waitForTimeout(500);
      
      // 店舗選択
      const storeButton = page.locator('button[wire\\:click*="duplicateCategoryToStore"]').first();
      if (await storeButton.count() > 0) {
        await storeButton.click();
        
        // 成功メッセージを確認
        await expect(page.locator('text=複製完了')).toBeVisible({ timeout: 5000 });
      }
    }
  });
});