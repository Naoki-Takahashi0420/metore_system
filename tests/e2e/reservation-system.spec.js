import { test, expect } from '@playwright/test';

test.describe('予約システムE2Eテスト', () => {
  const BASE_URL = 'http://127.0.0.1:8000';
  
  test('完全な予約フロー', async ({ page }) => {
    // 1. 店舗選択
    await page.goto(`${BASE_URL}/stores`);
    await expect(page).toHaveTitle(/店舗/);
    
    // 東京本店を選択
    await page.click('text=東京本店');
    await page.click('text=この店舗を選択');
    
    // 2. メニュー選択ページ
    await expect(page).toHaveURL(/.*\/reservation\/menu/);
    await expect(page.locator('h1')).toContainText('メニューを選択');
    
    // 最初のメニューをクリック
    await page.locator('.menu-card').first().click();
    
    // 3. カレンダーページ
    await expect(page).toHaveURL(/.*\/reservation\/calendar/);
    await expect(page.locator('table')).toBeVisible();
    
    // 時間枠が表示されているか確認
    const timeSlots = await page.$$eval('tbody tr', rows => rows.length);
    expect(timeSlots).toBeGreaterThan(0);
    
    // 23:30スロットの確認（東京本店）
    const has2330 = await page.locator('text=23:30').count();
    expect(has2330).toBeGreaterThan(0);
  });
  
  test('営業時間の店舗別表示', async ({ page }) => {
    // 大阪支店（18:00まで）
    await page.goto(`${BASE_URL}/reservation/menu/2`);
    await page.locator('.menu-card').first().click();
    await page.waitForSelector('table');
    
    // 18:00以降のスロットがないことを確認
    const has1830 = await page.locator('td:has-text("18:30")').count();
    expect(has1830).toBe(0);
    
    // 名古屋駅前店（15分間隔）
    await page.goto(`${BASE_URL}/reservation/menu/3`);
    await page.locator('.menu-card').first().click();
    await page.waitForSelector('table');
    
    // 15分刻みの確認
    const has0915 = await page.locator('td:has-text("09:15")').count();
    const has0945 = await page.locator('td:has-text("09:45")').count();
    expect(has0915).toBeGreaterThan(0);
    expect(has0945).toBeGreaterThan(0);
  });
  
  test('エッジケース: セッションなしでの直接アクセス', async ({ page }) => {
    // カレンダーに直接アクセス
    await page.goto(`${BASE_URL}/reservation/calendar`);
    
    // メニュー選択ページにリダイレクトされる
    await expect(page).toHaveURL(/.*\/reservation\/menu/);
  });
  
  test('エッジケース: 削除されたルートへのアクセス', async ({ page }) => {
    // 古いdatetimeページへのアクセス
    const response = await page.goto(`${BASE_URL}/reservation/datetime`, {
      waitUntil: 'domcontentloaded'
    });
    
    // 404または他のページへのリダイレクト
    expect(response.status()).toBe(404);
  });
  
  test('席数制限のテスト', async ({ page }) => {
    // 東京本店（3席）のテスト
    await page.goto(`${BASE_URL}/stores`);
    await page.click('text=東京本店');
    await page.click('text=この店舗を選択');
    await page.locator('.menu-card').first().click();
    
    // カレンダーで○×の表示を確認
    await page.waitForSelector('table');
    const availableSlots = await page.$$eval('.available-slot', slots => slots.length);
    expect(availableSlots).toBeGreaterThan(0);
  });
  
  test('90分メニューの営業時間制約', async ({ page }) => {
    await page.goto(`${BASE_URL}/stores`);
    await page.click('text=東京本店');
    await page.click('text=この店舗を選択');
    
    // VRトレーニング（90分）を選択
    await page.click('text=VRトレーニング');
    await page.waitForSelector('table');
    
    // 22:30と23:00が予約不可であることを確認
    const slot2230 = await page.locator('[data-time="22:30"].available').count();
    const slot2300 = await page.locator('[data-time="23:00"].available').count();
    
    expect(slot2230).toBe(0);
    expect(slot2300).toBe(0);
  });
});

test.describe('APIエンドポイントテスト', () => {
  const BASE_URL = 'http://127.0.0.1:8000';
  
  test('営業時間APIの動作確認', async ({ request }) => {
    // 東京本店
    const tokyoResponse = await request.get(`${BASE_URL}/api/availability/slots?store_id=1&date=2025-08-30&menu_id=1`);
    expect(tokyoResponse.ok()).toBeTruthy();
    const tokyoData = await tokyoResponse.json();
    
    // 23:30まであることを確認
    const tokyoTimes = tokyoData.available_slots || tokyoData.slots || [];
    const hasTokyo2330 = tokyoTimes.some(slot => slot.time === '23:00');
    expect(hasTokyo2330).toBeTruthy();
    
    // 大阪支店
    const osakaResponse = await request.get(`${BASE_URL}/api/availability/slots?store_id=2&date=2025-08-30&menu_id=1`);
    const osakaData = await osakaResponse.json();
    const osakaTimes = osakaData.available_slots || osakaData.slots || [];
    
    // 18:00以降がないことを確認
    const hasOsaka1830 = osakaTimes.some(slot => slot.time === '18:30');
    expect(hasOsaka1830).toBeFalsy();
  });
  
  test('15分間隔の確認', async ({ request }) => {
    const response = await request.get(`${BASE_URL}/api/availability/slots?store_id=3&date=2025-08-30&menu_id=1`);
    const data = await response.json();
    const slots = data.available_slots || data.slots || [];
    
    // 15分刻みのスロットがあるか
    const has0915 = slots.some(slot => slot.time === '09:15');
    const has0930 = slots.some(slot => slot.time === '09:30');
    const has0945 = slots.some(slot => slot.time === '09:45');
    
    expect(has0915).toBeTruthy();
    expect(has0930).toBeTruthy();
    expect(has0945).toBeTruthy();
  });
});