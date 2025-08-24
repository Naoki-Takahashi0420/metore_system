import { test, expect } from '@playwright/test';

test.describe('予約カレンダーテスト', () => {
  test('カレンダー表示と予約クリックテスト', async ({ page }) => {
    // 管理画面にログイン
    await page.goto('/admin/login');
    await page.getByLabel('メールアドレス').fill('superadmin@xsyumeno.com');
    await page.getByLabel('パスワード').fill('password');
    await page.getByRole('button', { name: 'ログイン' }).click();
    
    // 予約カレンダーページにアクセス
    await page.goto('/admin/reservation-calendars');
    
    // ページが読み込まれるまで待機
    await page.waitForTimeout(3000);
    
    // カレンダーが表示されることを確認
    await expect(page.locator('#calendar-container')).toBeVisible();
    
    // 予約イベントが表示されるまで待機
    await page.waitForSelector('.fc-event', { timeout: 10000 });
    
    // 予約イベントが存在することを確認
    const events = await page.locator('.fc-event').count();
    console.log(`予約イベント数: ${events}`);
    
    // 最初の予約イベントをクリック
    if (events > 0) {
      await page.locator('.fc-event').first().click();
      
      // モーダルが表示されるまで待機
      await page.waitForTimeout(1000);
      
      // モーダルが表示されることを確認
      const modalVisible = await page.locator('[role="dialog"]').isVisible();
      console.log(`モーダル表示状態: ${modalVisible}`);
      
      if (modalVisible) {
        // 予約詳細の内容を確認
        await expect(page.getByText('予約詳細')).toBeVisible();
        console.log('✓ モーダル表示成功');
      } else {
        console.log('✗ モーダルが表示されていません');
      }
    } else {
      console.log('予約イベントが見つかりません');
    }
    
    // ページのスクリーンショットを撮影
    await page.screenshot({ path: 'calendar-test.png', fullPage: true });
  });
});