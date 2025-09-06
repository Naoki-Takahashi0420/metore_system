import { test, expect } from '@playwright/test';

test('Check menu create page functionality', async ({ page }) => {
  test.setTimeout(60000);
  
  // ログイン
  await page.goto('http://127.0.0.1:8000/admin/login');
  await page.locator('#data\\.email').fill('admin@eye-training.com');
  await page.locator('#data\\.password').fill('password');
  await page.locator('button[type="submit"]').click();
  
  // ダッシュボードへのリダイレクトを待つ
  await page.waitForURL(/\/admin/, { timeout: 10000 });
  
  // メニュー作成ページへアクセス
  await page.goto('http://127.0.0.1:8000/admin/menus/create');
  await page.waitForLoadState('networkidle');
  
  // サブスクリプショントグルの存在確認
  const subscriptionToggle = await page.locator('text=サブスクリプションメニューとして提供').first();
  console.log('Subscription toggle found:', await subscriptionToggle.isVisible());
  
  // サブスクリプションをONにする
  if (await subscriptionToggle.isVisible()) {
    const toggle = await page.locator('button[role="switch"]').first();
    await toggle.click();
    await page.waitForTimeout(500);
    
    // サブスク料金設定が表示されるか確認
    const monthlyPriceVisible = await page.locator('text=月額料金').isVisible();
    console.log('Monthly price field visible after toggle ON:', monthlyPriceVisible);
    
    // 通常料金が非表示になるか確認
    const normalPriceVisible = await page.locator('text=通常メニュー料金設定').isVisible();
    console.log('Normal price section visible after toggle ON (should be false):', normalPriceVisible);
    
    // もう一度トグルをOFFにする
    await toggle.click();
    await page.waitForTimeout(500);
    
    // 通常料金設定が表示されるか確認
    const normalPriceVisibleAfterOff = await page.locator('text=通常メニュー料金設定').isVisible();
    console.log('Normal price section visible after toggle OFF (should be true):', normalPriceVisibleAfterOff);
  }
  
  // スクリーンショットを撮る
  await page.screenshot({ path: 'menu-create-page.png', fullPage: true });
  console.log('Screenshot saved as menu-create-page.png');
});