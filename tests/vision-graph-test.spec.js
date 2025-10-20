import { test, expect } from '@playwright/test';

test('vision graph accordion height test', async ({ page }) => {
  // ログイン
  await page.goto('http://localhost:8000/admin/login');
  await page.fill('input[type="email"]', 'admin@eye-training.com');
  await page.fill('input[type="password"]', 'password');
  await page.click('button[type="submit"]');
  await page.waitForURL('**/admin');

  // タイムラインページに移動
  await page.goto('http://localhost:8000/admin');

  // 予約をクリックしてモーダルを開く
  await page.waitForSelector('table', { timeout: 10000 });

  // 予約詳細モーダルを開く（セルをクリック）
  const cells = await page.locator('td[style*="background"]').all();
  if (cells.length > 0) {
    await cells[0].click();
    await page.waitForTimeout(1000);
  }

  // カルテ履歴ボタンを探してクリック
  const medicalHistoryButton = page.locator('button:has-text("カルテ履歴")');
  if (await medicalHistoryButton.count() > 0) {
    await medicalHistoryButton.click();
    await page.waitForTimeout(1000);

    // 視力推移グラフアコーディオンを開く
    const visionGraphButton = page.locator('button:has-text("視力推移グラフ")');
    if (await visionGraphButton.count() > 0) {
      await visionGraphButton.click();
      await page.waitForTimeout(1000);

      // アコーディオンコンテンツの高さを確認
      const accordionContent = page.locator('[x-show="visionGraphOpen"]').first();
      const boundingBox = await accordionContent.boundingBox();

      console.log('Accordion content height:', boundingBox?.height);

      // スクリーンショットを撮る
      await page.screenshot({ path: '/tmp/vision-graph-accordion.png', fullPage: true });

      if (boundingBox && boundingBox.height < 100) {
        console.error('WARNING: Accordion content height is too small!', boundingBox.height);
      }
    } else {
      console.log('Vision graph button not found');
    }
  } else {
    console.log('Medical history button not found');
  }
});
