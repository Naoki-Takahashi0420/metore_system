import { test, expect } from '@playwright/test';

test.describe('ログイン問題デバッグ', () => {
  const baseURL = 'http://13.115.38.179';
  
  test('詳細なログインデバッグ', async ({ page }) => {
    // コンソールログを取得
    page.on('console', msg => console.log('Browser console:', msg.text()));
    page.on('pageerror', error => console.log('Page error:', error.message));
    
    // ネットワークエラーを監視
    page.on('response', response => {
      if (response.status() >= 400) {
        console.log(`HTTP Error ${response.status()}: ${response.url()}`);
      }
    });

    console.log('1. ログインページにアクセス...');
    await page.goto(`${baseURL}/admin/login`);
    await page.screenshot({ path: 'test-results/1-login-page.png' });
    
    // ページのHTMLを確認
    const pageTitle = await page.title();
    console.log('ページタイトル:', pageTitle);
    
    // フォーム要素の存在確認
    const emailInput = page.locator('input[type="email"]');
    const passwordInput = page.locator('input[type="password"]');
    const submitButton = page.locator('button[type="submit"]');
    
    console.log('2. フォーム要素の確認...');
    console.log('Email input visible:', await emailInput.isVisible());
    console.log('Password input visible:', await passwordInput.isVisible());
    console.log('Submit button visible:', await submitButton.isVisible());
    
    // Livewireの存在確認
    const livewireScripts = await page.locator('script[src*="livewire"]').count();
    console.log('Livewire scripts found:', livewireScripts);
    
    console.log('3. ログイン情報を入力...');
    await emailInput.fill('admin@xsyumeno.com');
    await passwordInput.fill('password');
    await page.screenshot({ path: 'test-results/2-filled-form.png' });
    
    // CSRFトークンの確認
    const csrfToken = await page.locator('meta[name="csrf-token"]').getAttribute('content');
    console.log('CSRF Token exists:', !!csrfToken);
    
    console.log('4. ログインボタンをクリック...');
    
    // レスポンスを待つ
    const responsePromise = page.waitForResponse(response => 
      response.url().includes('/admin') || response.url().includes('livewire'),
      { timeout: 10000 }
    ).catch(e => {
      console.log('Response timeout:', e.message);
      return null;
    });
    
    await submitButton.click();
    
    const response = await responsePromise;
    if (response) {
      console.log('Response status:', response.status());
      console.log('Response URL:', response.url());
    }
    
    // 5秒待って現在のURLを確認
    await page.waitForTimeout(5000);
    console.log('5. 現在のURL:', page.url());
    await page.screenshot({ path: 'test-results/3-after-login.png' });
    
    // エラーメッセージの確認
    const errorMessages = await page.locator('.error, .alert, [role="alert"]').allTextContents();
    if (errorMessages.length > 0) {
      console.log('エラーメッセージ:', errorMessages);
    }
    
    // ページの内容を確認
    const pageContent = await page.content();
    if (pageContent.includes('500') || pageContent.includes('Error')) {
      console.log('ページにエラーが含まれています');
      
      // エラーの詳細を取得
      const bodyText = await page.locator('body').innerText();
      console.log('Body text (first 500 chars):', bodyText.substring(0, 500));
    }
    
    // ネットワークタブの内容を確認
    const cookies = await page.context().cookies();
    console.log('Cookies count:', cookies.length);
    
    // 最終確認
    const finalUrl = page.url();
    if (finalUrl.includes('/admin') && !finalUrl.includes('/login')) {
      console.log('✅ ログイン成功！');
    } else {
      console.log('❌ ログイン失敗 - まだログインページにいます');
    }
  });

  test('CURLでのログインテスト', async ({ page }) => {
    const { exec } = require('child_process');
    const util = require('util');
    const execPromise = util.promisify(exec);
    
    console.log('CURLでPOSTリクエストをテスト...');
    
    try {
      // まずGETでCSRFトークンを取得
      const getCommand = `curl -s -c /tmp/cookies.txt http://13.115.38.179/admin/login | grep 'csrf-token' | head -1`;
      const { stdout: csrfHtml } = await execPromise(getCommand);
      console.log('CSRF token HTML:', csrfHtml.substring(0, 200));
      
      // POSTでログイン試行
      const postCommand = `curl -v -X POST http://13.115.38.179/admin/login \
        -H "Content-Type: application/x-www-form-urlencoded" \
        -b /tmp/cookies.txt \
        -c /tmp/cookies.txt \
        -d "email=admin@xsyumeno.com&password=password" \
        -L 2>&1`;
      
      const { stdout: postResult } = await execPromise(postCommand);
      console.log('POST result:', postResult.substring(0, 1000));
      
      // HTTPステータスを確認
      if (postResult.includes('405')) {
        console.log('❌ 405 Method Not Allowed - POSTメソッドが許可されていません');
      } else if (postResult.includes('302') || postResult.includes('200')) {
        console.log('✅ HTTPレスポンスは正常');
      }
    } catch (error) {
      console.log('CURL test error:', error);
    }
  });
});