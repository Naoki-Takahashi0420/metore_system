import { test, expect } from '@playwright/test';

test.describe('パラメータベース予約フロー', () => {

  test('カルテからの予約で正しいメニューが表示される', async ({ page }) => {
    // 管理画面にログイン
    await page.goto('http://localhost:8000/admin/login');
    await page.fill('input[name="email"]', 'admin@eye-training.com');
    await page.fill('input[name="password"]', 'password');
    await page.click('button[type="submit"]');
    await page.waitForURL('**/admin');

    // カルテページへ
    await page.goto('http://localhost:8000/admin/customers');
    await page.waitForTimeout(1000);

    // 最初の顧客をクリック
    await page.click('table tbody tr:first-child');
    await page.waitForTimeout(500);

    // カルテから予約ボタンをクリック（パラメータ付きでリダイレクト）
    const customerId = await page.evaluate(() => {
      const customerData = localStorage.getItem('customer_data');
      if (customerData) {
        return JSON.parse(customerData).id;
      }
      return null;
    });

    // 店舗選択ページへ（source=medical パラメータ付き）
    await page.goto(`http://localhost:8000/stores?source=medical&customer_id=${customerId}`);

    // 店舗を選択
    await page.click('.store-card:first-child button');
    await page.waitForURL('**/reservation/select-category*');

    // URLにパラメータが引き継がれているか確認
    const categoryUrl = page.url();
    expect(categoryUrl).toContain('source=medical');
    expect(categoryUrl).toContain('customer_id=');

    // カテゴリーを選択
    await page.click('.category-form:first-child button');
    await page.waitForURL('**/reservation/select-time*');

    // メニューページでカルテ専用メニューが表示されているか確認
    const menuTexts = await page.locator('.menu-item-card h3').allTextContents();
    console.log('カルテから表示されるメニュー:', menuTexts);

    // カルテ専用メニューが存在するか確認
    const hasMedicalOnlyMenu = menuTexts.some(text =>
      text.includes('カルテからの予約のみ')
    );

    // 新規専用メニューが存在しないか確認
    const hasNewOnlyMenu = menuTexts.some(text =>
      text.includes('スタンダードコース')
    );

    expect(hasMedicalOnlyMenu).toBe(true);
    expect(hasNewOnlyMenu).toBe(false);
  });

  test('新規顧客の予約で正しいメニューが表示される', async ({ page }) => {
    // 直接店舗選択ページへ（パラメータなし）
    await page.goto('http://localhost:8000/stores');

    // URLにパラメータがないことを確認
    const storeUrl = page.url();
    expect(storeUrl).not.toContain('source=');
    expect(storeUrl).not.toContain('customer_id=');

    // 店舗を選択
    await page.click('.store-card:first-child button');
    await page.waitForURL('**/reservation/select-category');

    // カテゴリーを選択
    await page.click('.category-form:first-child button');
    await page.waitForURL('**/reservation/select-time');

    // メニューページで新規向けメニューが表示されているか確認
    const menuTexts = await page.locator('.menu-item-card h3').allTextContents();
    console.log('新規顧客に表示されるメニュー:', menuTexts);

    // 新規向けメニューが存在するか確認
    const hasNewMenu = menuTexts.some(text =>
      text.includes('スタンダードコース')
    );

    // カルテ専用メニューが存在しないか確認
    const hasMedicalOnlyMenu = menuTexts.some(text =>
      text.includes('カルテからの予約のみ')
    );

    expect(hasNewMenu).toBe(true);
    expect(hasMedicalOnlyMenu).toBe(false);
  });

  test('パラメータが全ページで正しく引き継がれる', async ({ page }) => {
    const testCustomerId = '123';
    const testSource = 'medical';

    // パラメータ付きで開始
    await page.goto(`http://localhost:8000/stores?source=${testSource}&customer_id=${testCustomerId}`);

    // 店舗選択
    await page.click('.store-card:first-child button');
    await page.waitForURL('**/reservation/select-category*');

    // カテゴリー選択ページでパラメータ確認
    let currentUrl = page.url();
    expect(currentUrl).toContain(`source=${testSource}`);
    expect(currentUrl).toContain(`customer_id=${testCustomerId}`);

    // カテゴリー選択
    await page.click('.category-form:first-child button');
    await page.waitForURL('**/reservation/select-time*');

    // メニュー選択ページでパラメータ確認
    currentUrl = page.url();
    expect(currentUrl).toContain(`source=${testSource}`);
    expect(currentUrl).toContain(`customer_id=${testCustomerId}`);

    // フォームの hidden input を確認
    const sourceInput = await page.locator('#reservationForm input[name="source"]').getAttribute('value');
    const customerIdInput = await page.locator('#reservationForm input[name="customer_id"]').getAttribute('value');

    expect(sourceInput).toBe(testSource);
    expect(customerIdInput).toBe(testCustomerId);
  });

});