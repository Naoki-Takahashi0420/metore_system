import { test, expect } from '@playwright/test';

test('メニューカテゴリページの詳細調査', async ({ page }) => {
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
  
  // ページのHTMLを取得
  const pageContent = await page.content();
  
  // テーブルの行を確認
  const tableRows = await page.locator('table tbody tr').all();
  console.log(`テーブル行数: ${tableRows.length}`);
  
  // 各行のアクションボタンを確認
  for (let i = 0; i < Math.min(tableRows.length, 3); i++) {
    const row = tableRows[i];
    const actionButtons = await row.locator('button').all();
    console.log(`行 ${i + 1} のボタン数: ${actionButtons.length}`);
    
    for (const button of actionButtons) {
      const title = await button.getAttribute('title');
      const text = await button.textContent();
      const wireClick = await button.getAttribute('wire:click');
      console.log(`  - ボタン: title="${title}", text="${text}", wire:click="${wireClick}"`);
    }
  }
  
  // 複製に関連する要素を探す
  console.log('\n=== 複製関連の要素 ===');
  console.log('duplicate を含む要素:', pageContent.includes('duplicate'));
  console.log('複製 を含む要素:', pageContent.includes('複製'));
  console.log('document-duplicate アイコン:', pageContent.includes('document-duplicate'));
  
  // スクリーンショット
  await page.screenshot({ path: 'test-results/menu-categories-debug.png', fullPage: true });
});