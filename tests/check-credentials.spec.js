import { test, expect } from '@playwright/test';

test('Check login credentials and process', async ({ page }) => {
  test.setTimeout(60000);
  
  console.log('Starting credential check...');
  
  // ログインページへアクセス
  await page.goto('http://127.0.0.1:8000/admin/login', {
    waitUntil: 'networkidle'
  });
  
  console.log('Current URL before login:', page.url());
  
  // メールフィールドに値を入力
  const emailField = page.locator('#data\\.email');
  await emailField.fill('superadmin@eye-training.com');
  const emailValue = await emailField.inputValue();
  console.log('Email field value:', emailValue);
  
  // パスワードフィールドに値を入力
  const passwordField = page.locator('#data\\.password');
  await passwordField.fill('password');
  const passwordValue = await passwordField.inputValue();
  console.log('Password field value (length):', passwordValue.length);
  
  // ネットワークレスポンスを監視
  const responsePromise = page.waitForResponse(
    response => response.url().includes('/admin/login') && response.request().method() === 'POST',
    { timeout: 30000 }
  ).catch(() => null);
  
  // ログインボタンをクリック
  console.log('Clicking login button...');
  await page.locator('button[type="submit"]').click();
  
  // レスポンスを待つ
  const response = await responsePromise;
  if (response) {
    console.log('Login response status:', response.status());
    console.log('Login response URL:', response.url());
    
    // レスポンスボディを取得
    try {
      const body = await response.text();
      if (body.includes('error') || body.includes('失敗')) {
        console.log('Login response contains error');
      }
      // JSONレスポンスの場合
      if (response.headers()['content-type']?.includes('application/json')) {
        const json = JSON.parse(body);
        console.log('Response JSON:', JSON.stringify(json, null, 2));
      }
    } catch (e) {
      console.log('Could not parse response body');
    }
  } else {
    console.log('No login POST response captured');
  }
  
  // 少し待つ
  await page.waitForTimeout(3000);
  
  // 現在のURLを確認
  console.log('Current URL after login attempt:', page.url());
  
  // エラーメッセージを探す
  const errorMessages = await page.locator('.text-red-600, .text-danger, [role="alert"]').allTextContents();
  if (errorMessages.length > 0) {
    console.log('Error messages found:', errorMessages);
  }
  
  // ページのコンテンツを確認
  const pageTitle = await page.title();
  console.log('Page title after login:', pageTitle);
  
  // ダッシュボードの要素を探す
  const hasDashboard = await page.locator('text=/ダッシュボード|Dashboard/i').count() > 0;
  console.log('Dashboard element found:', hasDashboard);
  
  // 別の認証情報を試す
  if (!hasDashboard) {
    console.log('\nTrying alternative credentials...');
    await page.goto('http://127.0.0.1:8000/admin/login', {
      waitUntil: 'networkidle'
    });
    
    await page.locator('#data\\.email').fill('admin@eye-training.com');
    await page.locator('#data\\.password').fill('password');
    await page.locator('button[type="submit"]').click();
    
    await page.waitForTimeout(3000);
    console.log('URL after second attempt:', page.url());
    
    const hasDashboard2 = await page.locator('text=/ダッシュボード|Dashboard/i').count() > 0;
    console.log('Dashboard found after second attempt:', hasDashboard2);
  }
});