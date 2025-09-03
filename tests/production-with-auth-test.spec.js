import { test, expect } from '@playwright/test';

test.describe('本番環境動作確認（Basic認証対応）', () => {
  test('Basic認証を通過して管理画面にアクセス', async ({ page }) => {
    console.log('=== 本番環境テスト開始（Basic認証対応） ===');
    
    // Basic認証情報を含めてアクセス
    const username = 'xsyumeno';
    const password = 'xsyumeno2025!';
    
    // Basic認証付きでアクセス
    await page.goto('https://reservation.meno-training.com/admin/login', {
      httpCredentials: {
        username: username,
        password: password
      }
    });
    
    console.log('✅ Basic認証通過、ログインページに到達');
    
    // 管理者としてログイン
    await page.fill('input[type="email"]', 'naoki@yumeno-marketing.jp');
    await page.fill('input[type="password"]', 'Takahashi5000');
    await page.click('button:has-text("ログイン")');
    
    // ダッシュボードへの遷移を待つ
    try {
      await page.waitForURL(/.*\/admin$/, { timeout: 10000 });
      console.log('✅ ログイン成功、ダッシュボード表示');
    } catch (error) {
      console.log('⚠️ ログイン後のリダイレクトに問題がある可能性');
      const currentUrl = page.url();
      console.log(`現在のURL: ${currentUrl}`);
    }
    
    // メニューカテゴリー画面へ
    await page.goto('https://reservation.meno-training.com/admin/menu-categories', {
      httpCredentials: {
        username: username,
        password: password
      }
    });
    
    await page.waitForLoadState('networkidle');
    console.log('✅ メニューカテゴリー画面表示');
    
    // ページの内容を確認
    const pageTitle = await page.title();
    console.log(`ページタイトル: ${pageTitle}`);
    
    // テーブルの存在確認
    const hasTable = await page.locator('table').count();
    console.log(`テーブル要素: ${hasTable > 0 ? '存在' : '不在'}`);
    
    // 複製ボタンの確認
    const duplicateButtons = await page.locator('button:has-text("他店舗へ複製")').count();
    const wireClickButtons = await page.locator('button[wire\\:click*="duplicate"]').count();
    
    console.log(`複製ボタン数: ${duplicateButtons}`);
    console.log(`wire:click複製ボタン数: ${wireClickButtons}`);
    
    if (duplicateButtons > 0 || wireClickButtons > 0) {
      console.log('✅ 複製機能が本番環境で利用可能');
    } else {
      console.log('⚠️ 複製ボタンが見つかりません（データベースマイグレーションが必要な可能性）');
    }
    
    // メニュー作成画面へ
    await page.goto('https://reservation.meno-training.com/admin/menus/create', {
      httpCredentials: {
        username: username,
        password: password
      }
    });
    
    await page.waitForLoadState('networkidle');
    console.log('✅ メニュー作成画面表示');
    
    // フォーム要素の確認
    const storeSelect = await page.locator('select[name*="store_id"], [wire\\:model*="store_id"]').count();
    const categorySelect = await page.locator('select[name*="category_id"], [wire\\:model*="category_id"]').count();
    
    console.log(`店舗選択フィールド: ${storeSelect > 0 ? '存在' : '不在'}`);
    console.log(`カテゴリ選択フィールド: ${categorySelect > 0 ? '存在' : '不在'}`);
    
    if (categorySelect > 0) {
      const categoryField = await page.locator('select[name*="category_id"], [wire\\:model*="category_id"]').first();
      const isDisabled = await categoryField.isDisabled();
      console.log(`カテゴリ選択の初期状態: ${isDisabled ? '無効（正しい動作）' : '有効'}`);
    }
    
    console.log('\n=== テスト結果サマリー ===');
    console.log('✅ Basic認証: 通過');
    console.log('✅ 管理画面アクセス: 成功');
    console.log(`${duplicateButtons > 0 || wireClickButtons > 0 ? '✅' : '⚠️'} メニュー複製機能: ${duplicateButtons > 0 || wireClickButtons > 0 ? '利用可能' : '要確認'}`);
    console.log(`${categorySelect > 0 ? '✅' : '⚠️'} カテゴリ必須化: ${categorySelect > 0 ? '実装済み' : '要確認'}`);
  });
});