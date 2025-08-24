import { test, expect } from '@playwright/test';

test('予約送信のデバッグテスト', async ({ page }) => {
  console.log('🔍 予約送信デバッグ開始');
  
  // コンソールエラーを監視
  page.on('console', msg => {
    if (msg.type() === 'error') {
      console.error('❌ ブラウザエラー:', msg.text());
    }
  });
  
  // レスポンスを監視
  page.on('response', response => {
    if (response.url().includes('/reservation/store')) {
      console.log(`📡 レスポンス: ${response.status()} ${response.statusText()}`);
      console.log(`📍 URL: ${response.url()}`);
    }
  });
  
  // メニュー選択
  await page.goto('http://127.0.0.1:8000/reservation/menu');
  await page.locator('.bg-white.rounded-lg.shadow-sm').first().click();
  await page.waitForURL('**/reservation');
  
  // 時間枠選択
  const slot = page.locator('button.time-slot').first();
  await slot.click();
  await page.waitForSelector('#reservationForm', { state: 'visible' });
  
  // フォーム入力
  await page.fill('input[name="last_name"]', 'テスト');
  await page.fill('input[name="first_name"]', '太郎');
  await page.fill('input[name="phone"]', '090-9999-9999');
  await page.fill('input[name="email"]', 'test@test.com');
  
  // CSRFトークンの確認
  const csrfToken = await page.locator('input[name="_token"]').inputValue();
  console.log(`🔑 CSRFトークン: ${csrfToken ? '存在' : '不在'}`);
  
  // フォームのアクションURL確認
  const formAction = await page.locator('form').first().getAttribute('action');
  console.log(`📝 フォームアクション: ${formAction}`);
  
  // 送信ボタンクリック
  console.log('📤 フォーム送信...');
  await Promise.all([
    page.waitForNavigation({ timeout: 15000 }).catch(e => console.log('❌ Navigation error:', e.message)),
    page.locator('button[type="submit"]').filter({ hasText: '予約する' }).click()
  ]);
  
  // 現在のURLを確認
  const currentUrl = page.url();
  console.log(`📍 現在のURL: ${currentUrl}`);
  
  // エラーメッセージを探す
  const errorElements = await page.locator('.alert, .error, .text-red-500').all();
  for (const element of errorElements) {
    const text = await element.textContent();
    if (text && text.trim()) {
      console.log(`⚠️ エラーメッセージ: ${text}`);
    }
  }
  
  // 成功メッセージを探す
  const successElements = await page.locator('.alert-success, .text-green-500, h1').all();
  for (const element of successElements) {
    const text = await element.textContent();
    if (text && text.includes('完了')) {
      console.log(`✅ 成功メッセージ: ${text}`);
    }
  }
});