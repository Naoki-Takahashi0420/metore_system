import { test, expect } from '@playwright/test';

test('メニューカテゴリ複製の基本動作確認', async ({ page }) => {
  // スクリーンショットを有効化
  const screenshotPath = 'test-results/menu-duplication/';
  
  // 管理画面にログイン
  await page.goto('http://127.0.0.1:8002/admin/login');
  await page.screenshot({ path: `${screenshotPath}1-login.png` });
  
  // Filamentのログインフォームは data.email と data.password を使用
  await page.fill('input[type="email"]', 'admin@eye-training.com');
  await page.fill('input[type="password"]', 'password');
  await page.click('button:has-text("ログイン")');
  
  // ダッシュボードに遷移
  await page.waitForURL(/.*\/admin$/);
  await page.screenshot({ path: `${screenshotPath}2-dashboard.png` });
  
  // メニューカテゴリー画面へ
  await page.goto('http://127.0.0.1:8002/admin/menu-categories');
  await page.waitForLoadState('networkidle');
  await page.screenshot({ path: `${screenshotPath}3-categories.png` });
  
  // ページの内容を確認
  const pageContent = await page.content();
  console.log('=== ページに複製ボタンが存在するか確認 ===');
  console.log('複製ボタンの存在:', pageContent.includes('duplicate'));
  console.log('document-duplicate アイコン:', pageContent.includes('document-duplicate'));
  
  // 複製アクションボタンを探す
  const duplicateActions = await page.locator('[title*="複製"], button:has-text("複製"), [wire\\:click*="duplicate"]').all();
  console.log(`複製アクション数: ${duplicateActions.length}`);
  
  if (duplicateActions.length > 0) {
    // 最初の複製アクションをクリック
    await duplicateActions[0].click();
    await page.waitForTimeout(2000);
    await page.screenshot({ path: `${screenshotPath}4-after-click.png` });
    
    // モーダルやドロップダウンの内容を確認
    const modalContent = await page.content();
    console.log('モーダル表示:', modalContent.includes('target_store'));
  } else {
    console.log('複製ボタンが見つかりません');
  }
});