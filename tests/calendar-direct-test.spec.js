import { test, expect } from '@playwright/test';

test('カレンダーへの直接テスト', async ({ page }) => {
  // セッションをセットアップするため、一度フローを通る
  await page.goto('http://127.0.0.1:8000/reservation/store');
  
  // フォームを直接送信してセッションを作成
  await page.evaluate(() => {
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = '/reservation/store-selection';
    
    const token = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const tokenInput = document.createElement('input');
    tokenInput.type = 'hidden';
    tokenInput.name = '_token';
    tokenInput.value = token;
    
    const storeInput = document.createElement('input');
    storeInput.type = 'hidden';
    storeInput.name = 'store_id';
    storeInput.value = '1';
    
    form.appendChild(tokenInput);
    form.appendChild(storeInput);
    document.body.appendChild(form);
    form.submit();
  });
  
  await page.waitForURL('**/reservation/category');
  
  // カテゴリー選択
  await page.locator('button[type="submit"]').first().click();
  await page.waitForTimeout(1000);
  
  // メニュー選択
  await page.locator('button:has-text("予約する")').first().click();
  await page.waitForTimeout(2000);
  
  // 現在のURLを確認
  const currentUrl = page.url();
  console.log('Current URL:', currentUrl);
  
  // カレンダーページにいるか確認
  if (currentUrl.includes('/reservation/calendar')) {
    // ○と×の数を確認
    const availableSlots = await page.locator('.time-slot:not(.unavailable)').count();
    const unavailableSlots = await page.locator('.time-slot.unavailable').count();
    
    console.log('Available slots:', availableSlots);
    console.log('Unavailable slots:', unavailableSlots);
    
    await page.screenshot({ path: 'calendar-direct.png', fullPage: true });
    
    // 少なくともいくつかの利用可能なスロットがあるはず
    expect(availableSlots).toBeGreaterThan(0);
  } else {
    console.log('Not on calendar page, redirected to:', currentUrl);
  }
});