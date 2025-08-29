import { test, expect } from '@playwright/test';

test('ガントチャート表示確認', async ({ page }) => {
  // 管理画面にログイン
  await page.goto('http://127.0.0.1:8000/admin/login');
  
  // ページが完全に読み込まれるのを待つ
  await page.waitForLoadState('domcontentloaded');
  await page.waitForTimeout(1000);
  
  // 入力フィールドを明示的に待機してからフィル
  await page.waitForSelector('input[id="data.email"]', { visible: true });
  await page.click('input[id="data.email"]');
  await page.fill('input[id="data.email"]', 'naoki@yumeno-marketing.jp');
  
  await page.waitForSelector('input[id="data.password"]', { visible: true });
  await page.click('input[id="data.password"]');
  await page.fill('input[id="data.password"]', 'Takahashi5000');
  
  await page.click('button[type="submit"]');
  
  // ログイン成功を確認
  await expect(page).toHaveURL(/.*admin/);
  
  // 予約カレンダーに移動
  try {
    await page.goto('http://127.0.0.1:8000/admin/reservation-calendars', { waitUntil: 'networkidle' });
  } catch (error) {
    console.log('Navigation error:', error.message);
    // エラー時はダッシュボードから移動を試みる
    await page.click('text=予約管理');
    await page.waitForTimeout(1000);
    await page.click('text=予約カレンダー');
  }
  
  // ページが読み込まれるのを待つ
  await page.waitForTimeout(3000);
  
  // ガントチャート関連要素の存在確認
  await expect(page.locator('text=予約スケジュール')).toBeVisible();
  
  // テーブルの存在確認
  const table = page.locator('table');
  await expect(table).toBeVisible();
  
  // 予約セルの確認
  const reservationCells = page.locator('.reservation-cell');
  console.log('Reservation cells count:', await reservationCells.count());
  
  // 最初の予約セルをクリックしてモーダルテスト
  if (await reservationCells.count() > 0) {
    console.log('Clicking first reservation cell...');
    await reservationCells.first().click();
    
    // モーダルが開くまで待機
    await page.waitForTimeout(1000);
    
    // モーダルの存在確認
    const modal = page.locator('#reservationModal');
    const isModalVisible = await modal.isVisible();
    console.log('Modal visible:', isModalVisible);
    
    if (isModalVisible) {
      console.log('✅ モーダル正常に表示されました');
      
      // カルテボタンの確認
      const chartButton = page.locator('text=カルテを見る');
      console.log('Chart button visible:', await chartButton.isVisible());
      
      // モーダルを閉じる
      await page.locator('text=閉じる').click();
    } else {
      console.log('❌ モーダルが表示されませんでした');
      
      // JavaScriptエラーをチェック
      page.on('console', msg => console.log('Console:', msg.text()));
      page.on('pageerror', error => console.log('Page error:', error.message));
    }
  }
  
  // スクリーンショットを撮影
  await page.screenshot({ 
    path: 'gantt-chart-modal-test.png', 
    fullPage: true 
  });
  
  console.log('モーダルテストのスクリーンショットを撮影しました: gantt-chart-modal-test.png');
});