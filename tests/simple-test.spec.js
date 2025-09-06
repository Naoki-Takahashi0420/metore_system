import { test, expect } from '@playwright/test';

test('Basic connection test', async ({ page }) => {
  // タイムアウトを30秒に設定
  test.setTimeout(30000);
  
  // 基本的な接続テスト
  await page.goto('http://localhost:8000/admin/login', {
    waitUntil: 'networkidle',
    timeout: 20000
  });
  
  // ページのタイトルを確認
  const title = await page.title();
  console.log('Page title:', title);
  
  // ログインフォーム要素を確認
  const emailInput = await page.$('input[type="email"], input[name="email"]');
  const passwordInput = await page.$('input[type="password"], input[name="password"]');
  
  if (emailInput && passwordInput) {
    console.log('✅ Login form found');
  } else {
    console.log('❌ Login form not found');
    // ページのHTMLを取得して確認
    const html = await page.content();
    console.log('Page HTML (first 500 chars):', html.substring(0, 500));
  }
});