import { test, expect } from '@playwright/test';

test('レイアウト幅の確認', async ({ page }) => {
  // フルHD画面サイズ
  await page.setViewportSize({ width: 1920, height: 1080 });
  
  await page.goto('http://127.0.0.1:8000/stores');
  await page.waitForTimeout(2000);
  
  // コンテナの幅と位置を確認
  const container = await page.locator('.max-w-7xl').first();
  const box = await container.boundingBox();
  
  console.log('Container width:', box?.width);
  console.log('Container left:', box?.x);
  console.log('Viewport width:', 1920);
  console.log('Center position:', box ? (box.x + box.width/2) : 'N/A');
  console.log('Expected center:', 960);
  
  // 店舗カードの表示を確認
  const storeCards = await page.locator('#stores-container > div').count();
  console.log('Store cards count:', storeCards);
  
  // max-w-7xlは1280pxなので、1920pxの画面では左右に320pxずつ余白があるはず
  if (box) {
    const expectedLeft = (1920 - 1280) / 2;
    console.log('Expected left margin:', expectedLeft);
    console.log('Actual left margin:', box.x);
  }
});