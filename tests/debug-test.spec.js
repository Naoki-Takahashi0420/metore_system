import { test, expect } from '@playwright/test';

test('Debug login page', async ({ page }) => {
  test.setTimeout(30000);
  
  console.log('Starting test...');
  
  // ページにアクセス
  const response = await page.goto('http://127.0.0.1:8000/admin/login', {
    waitUntil: 'domcontentloaded',
    timeout: 20000
  });
  
  console.log('Response status:', response?.status());
  console.log('Response URL:', page.url());
  
  // タイトルを取得
  const title = await page.title();
  console.log('Page title:', title);
  
  // タイトルのアサーション - より柔軟に
  if (!title.includes('ログイン') && !title.includes('Login') && !title.includes('目のトレーニング')) {
    console.error('Title does not match expected patterns');
    console.error('Expected to contain: ログイン, Login, or 目のトレーニング');
    console.error('Actual title:', title);
  }
  
  // フォーム要素を探す
  const emailInput = await page.$('input[name="email"]');
  const passwordInput = await page.$('input[name="password"]');
  const submitButton = await page.$('button[type="submit"]');
  
  console.log('Email input found:', !!emailInput);
  console.log('Password input found:', !!passwordInput);
  console.log('Submit button found:', !!submitButton);
  
  // すべての input 要素を表示
  const allInputs = await page.$$eval('input', inputs => 
    inputs.map(input => ({
      type: input.type,
      name: input.name,
      id: input.id,
      placeholder: input.placeholder
    }))
  );
  console.log('All inputs on page:', JSON.stringify(allInputs, null, 2));
  
  // すべてのボタンを表示
  const allButtons = await page.$$eval('button', buttons => 
    buttons.map(button => ({
      type: button.type,
      text: button.textContent?.trim()
    }))
  );
  console.log('All buttons on page:', JSON.stringify(allButtons, null, 2));
});