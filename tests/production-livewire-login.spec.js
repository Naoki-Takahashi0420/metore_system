import { test, expect } from '@playwright/test';

test('本番環境Livewireログインテスト', async ({ page }) => {
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
  
  // Livewireフォームに入力
  await page.locator('[wire\\:model="data.email"]').fill('admin@eye-training.com');
  await page.locator('[wire\\:model="data.password"]').fill('password');
  
  // 送信ボタンをクリック
  await page.locator('button[type="submit"]').click();
  
  // ログイン処理を待つ
  await page.waitForTimeout(5000);
  
  const currentUrl = page.url();
  console.log(`現在のURL: ${currentUrl}`);
  
  if (currentUrl.includes('/admin') && !currentUrl.includes('/login')) {
    console.log('✅ ログイン成功！');
    
    // メニューカテゴリーページへ
    await page.goto('https://reservation.meno-training.com/admin/menu-categories', {
      httpCredentials: {
        username: username,
        password: password
      }
    });
    
    await page.waitForLoadState('networkidle');
    
    // ページタイトル確認
    const title = await page.title();
    console.log(`ページタイトル: ${title}`);
    
    // テーブルの確認
    const tables = await page.locator('table').count();
    console.log(`テーブル数: ${tables}`);
    
    if (tables > 0) {
      // 複製アクションの確認
      const duplicateButtons = await page.locator('button').all();
      let hasDuplicate = false;
      
      for (let button of duplicateButtons) {
        const wireClick = await button.getAttribute('wire:click');
        if (wireClick && wireClick.includes('duplicate')) {
          hasDuplicate = true;
          console.log(`✅ 複製ボタン発見: ${wireClick}`);
          break;
        }
      }
      
      if (!hasDuplicate) {
        console.log('⚠️ 複製ボタンが見つかりません');
      }
      
      // テーブルの行数を確認
      const rows = await page.locator('table tbody tr').count();
      console.log(`カテゴリー数: ${rows}`);
    }
    
    // メニュー作成ページへ
    await page.goto('https://reservation.meno-training.com/admin/menus/create', {
      httpCredentials: {
        username: username,
        password: password
      }
    });
    
    await page.waitForLoadState('networkidle');
    
    // フォームフィールドの確認
    const storeSelect = await page.locator('select[wire\\:model*="store_id"], select[name*="store_id"]').count();
    const categorySelect = await page.locator('select[wire\\:model*="category_id"], select[name*="category_id"]').count();
    
    console.log(`\n=== メニュー作成フォーム ===`);
    console.log(`店舗選択: ${storeSelect > 0 ? '✅ あり' : '⚠️ なし'}`);
    console.log(`カテゴリ選択: ${categorySelect > 0 ? '✅ あり' : '⚠️ なし'}`);
    
    if (categorySelect > 0) {
      const categoryField = await page.locator('select[wire\\:model*="category_id"], select[name*="category_id"]').first();
      const isDisabled = await categoryField.isDisabled();
      console.log(`カテゴリ必須化: ${isDisabled ? '✅ 動作中（店舗未選択時は無効）' : '⚠️ 常に有効'}`);
    }
    
    console.log('\n✅ 本番環境のテスト完了');
  } else {
    console.log('❌ ログインに失敗しました');
    
    // エラーメッセージを確認
    const alerts = await page.locator('[role="alert"], .filament-notifications-notification').all();
    for (let alert of alerts) {
      const text = await alert.textContent();
      console.log(`エラー: ${text}`);
    }
  }
});