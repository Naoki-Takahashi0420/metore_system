import { test, expect } from '@playwright/test';

test('本番環境ログインデバッグ', async ({ page }) => {
  const username = 'xsyumeno';
  const password = 'xsyumeno2025!';
  
  // Basic認証付きでアクセス
  await page.goto('https://reservation.meno-training.com/admin/login', {
    httpCredentials: {
      username: username,
      password: password
    }
  });
  
  console.log('✅ Basic認証通過');
  
  // ページ内容を確認
  await page.screenshot({ path: 'test-results/production-login-page.png' });
  
  // フォーム要素の詳細確認
  const emailInputs = await page.locator('input[type="email"]').all();
  const passwordInputs = await page.locator('input[type="password"]').all();
  const submitButtons = await page.locator('button[type="submit"]').all();
  
  console.log(`Email入力フィールド数: ${emailInputs.length}`);
  console.log(`Password入力フィールド数: ${passwordInputs.length}`);
  console.log(`送信ボタン数: ${submitButtons.length}`);
  
  // Livewireフォームの可能性を確認
  const wireModelInputs = await page.locator('[wire\\:model]').all();
  console.log(`wire:model要素数: ${wireModelInputs.length}`);
  
  for (let input of wireModelInputs) {
    const model = await input.getAttribute('wire:model');
    const type = await input.getAttribute('type');
    console.log(`  - wire:model="${model}", type="${type}"`);
  }
  
  // ログイン試行
  if (emailInputs.length > 0 && passwordInputs.length > 0) {
    await emailInputs[0].fill('naoki@yumeno-marketing.jp');
    await passwordInputs[0].fill('Takahashi5000');
    
    // Enterキーで送信
    await passwordInputs[0].press('Enter');
    
    // 結果を待つ
    await page.waitForTimeout(3000);
    
    const afterLoginUrl = page.url();
    console.log(`ログイン後のURL: ${afterLoginUrl}`);
    
    if (afterLoginUrl.includes('/admin/login')) {
      console.log('⚠️ ログインページに留まっている');
      
      // エラーメッセージを探す
      const errorMessages = await page.locator('.error, .alert, [role="alert"], .text-red-500, .text-danger').all();
      for (let error of errorMessages) {
        const text = await error.textContent();
        if (text && text.trim()) {
          console.log(`エラーメッセージ: ${text.trim()}`);
        }
      }
    } else if (afterLoginUrl.includes('/admin')) {
      console.log('✅ ログイン成功！');
      
      // ダッシュボードの内容を確認
      await page.screenshot({ path: 'test-results/production-dashboard.png' });
      
      // メニューカテゴリーページへ移動
      await page.goto('https://reservation.meno-training.com/admin/menu-categories', {
        httpCredentials: {
          username: username,
          password: password
        }
      });
      
      await page.waitForLoadState('networkidle');
      await page.screenshot({ path: 'test-results/production-menu-categories.png' });
      
      // 複製ボタンの確認
      const duplicateActions = await page.locator('button[wire\\:click*="duplicate"], button:has-text("他店舗へ複製")').count();
      console.log(`複製機能: ${duplicateActions > 0 ? '✅ 利用可能' : '⚠️ 見つかりません'}`);
    }
  }
});