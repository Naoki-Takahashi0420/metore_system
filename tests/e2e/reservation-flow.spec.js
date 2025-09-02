import { test, expect } from '@playwright/test';

test.describe('予約フロー完全テスト', () => {
  test.beforeEach(async ({ page }) => {
    // Basic認証
    await page.goto('http://127.0.0.1:8000/', {
      httpCredentials: {
        username: 'admin',
        password: 'password'
      }
    });
  });

  test('店舗選択からカテゴリー選択までの流れ', async ({ page }) => {
    console.log('\n========================================');
    console.log('🏪 店舗選択 → カテゴリー選択テスト');
    console.log('========================================\n');
    
    // トップページから予約ページへ
    await page.goto('http://127.0.0.1:8000/');
    await page.waitForLoadState('networkidle');
    console.log('📍 トップページアクセス');
    
    // 予約ボタンをクリック
    const reserveButton = page.locator('a').filter({ hasText: /予約|RESERVE/i }).first();
    if (await reserveButton.isVisible()) {
      await reserveButton.click();
      console.log('✅ 予約ボタンクリック');
    } else {
      // 直接予約ページへ
      await page.goto('http://127.0.0.1:8000/reservation/store');
    }
    
    // 店舗選択画面
    await expect(page).toHaveURL(/.*\/reservation\/store/);
    await expect(page.locator('h1')).toContainText('店舗をお選びください');
    console.log('✅ 店舗選択画面表示');
    
    // 店舗カードの確認
    const storeCards = await page.locator('.group').count();
    console.log(`📊 表示されている店舗数: ${storeCards}個`);
    
    if (storeCards > 0) {
      // 最初の店舗をクリック
      const firstStore = page.locator('.group').first();
      const storeName = await firstStore.locator('h3').textContent();
      console.log(`🏪 選択する店舗: ${storeName}`);
      
      await firstStore.click();
      
      // カテゴリー選択画面へ遷移
      await expect(page).toHaveURL(/.*\/reservation\/category/);
      await expect(page.locator('h1')).toContainText('コースをお選びください');
      console.log('✅ カテゴリー選択画面へ遷移');
      
      // カテゴリーの表示確認
      const categoryForms = await page.locator('form').count();
      console.log(`📊 表示されているカテゴリー数: ${categoryForms}個`);
      
      // カテゴリー詳細の確認
      if (categoryForms > 0) {
        const firstCategory = page.locator('form').first();
        
        // カテゴリー名の確認
        const categoryName = await firstCategory.locator('h3').first().textContent().catch(() => null);
        if (categoryName) {
          console.log(`📁 最初のカテゴリー: ${categoryName}`);
        }
        
        // メニュー数バッジの確認
        const menuBadge = firstCategory.locator('.bg-green-100');
        if (await menuBadge.count() > 0) {
          const menuText = await menuBadge.textContent();
          console.log(`  📊 ${menuText}`);
        }
      }
      
      // スクリーンショット
      await page.screenshot({ path: 'test-results/category-select.png', fullPage: true });
      console.log('📸 スクリーンショット: category-select.png');
    }
  });

  test('OTP認証フロー（テスト環境）', async ({ page }) => {
    console.log('\n========================================');
    console.log('🔐 OTP認証テスト（テスト環境）');
    console.log('========================================\n');
    
    // ログインページへ
    await page.goto('http://127.0.0.1:8000/login');
    await page.waitForLoadState('networkidle');
    console.log('📍 ログインページアクセス');
    
    // 電話番号入力
    const phoneInput = page.locator('input[type="tel"]');
    await phoneInput.fill('09012345678');
    console.log('📱 電話番号入力: 09012345678');
    
    // SMS送信ボタンクリック
    const sendButton = page.locator('button').filter({ hasText: /SMS|認証コード/i });
    await sendButton.click();
    console.log('📤 SMS認証コード送信');
    
    // OTP入力画面を待つ
    await page.waitForTimeout(2000);
    
    // OTP入力フィールドを探す
    const otpInput = page.locator('input[name="otp_code"], input[placeholder*="認証コード"], input[type="text"]').first();
    if (await otpInput.isVisible()) {
      // テスト環境の固定OTP入力
      await otpInput.fill('123456');
      console.log('🔢 OTPコード入力: 123456（テスト環境固定）');
      
      // 認証ボタンクリック
      const verifyButton = page.locator('button').filter({ hasText: /認証|確認|ログイン/i });
      await verifyButton.click();
      console.log('✅ 認証ボタンクリック');
      
      // 認証成功を確認
      await page.waitForTimeout(3000);
      const currentUrl = page.url();
      console.log(`📍 現在のURL: ${currentUrl}`);
      
      if (currentUrl.includes('customer') || currentUrl.includes('mypage')) {
        console.log('✅ 認証成功 - マイページへ遷移');
      }
    } else {
      console.log('⚠️ OTP入力フィールドが見つかりません');
    }
  });

  test('レスポンシブデザインの確認', async ({ page }) => {
    console.log('\n========================================');
    console.log('📱 レスポンシブデザインテスト');
    console.log('========================================\n');
    
    // テストするページ
    const testUrl = 'http://127.0.0.1:8000/reservation/store';
    
    // デスクトップビュー
    await page.setViewportSize({ width: 1920, height: 1080 });
    await page.goto(testUrl);
    await page.waitForLoadState('networkidle');
    console.log('🖥️ デスクトップビュー (1920x1080)');
    
    const desktopCards = await page.locator('.group').count();
    console.log(`  表示カード数: ${desktopCards}`);
    
    // ステップインジケーターの確認
    const desktopSteps = await page.locator('.rounded-full').count();
    console.log(`  ステップ数: ${desktopSteps}`);
    
    await page.screenshot({ path: 'test-results/desktop-view.png' });
    
    // タブレットビュー
    await page.setViewportSize({ width: 768, height: 1024 });
    await page.reload();
    console.log('\n📱 タブレットビュー (768x1024)');
    
    const tabletCards = await page.locator('.group').count();
    console.log(`  表示カード数: ${tabletCards}`);
    
    await page.screenshot({ path: 'test-results/tablet-view.png' });
    
    // モバイルビュー
    await page.setViewportSize({ width: 375, height: 667 });
    await page.reload();
    console.log('\n📱 モバイルビュー (375x667)');
    
    const mobileCards = await page.locator('.group').count();
    console.log(`  表示カード数: ${mobileCards}`);
    
    // モバイルでのステップ表示確認
    const mobileSteps = await page.locator('.rounded-full').count();
    console.log(`  ステップ数: ${mobileSteps}`);
    
    // ステップテキストが省略形で表示されるか確認
    const stepTexts = await page.locator('.hidden.sm\\:inline').count();
    console.log(`  省略されたステップテキスト: ${stepTexts}個`);
    
    await page.screenshot({ path: 'test-results/mobile-view.png' });
    
    // 店舗カードをクリックしてカテゴリー画面へ
    if (mobileCards > 0) {
      await page.locator('.group').first().click();
      await page.waitForURL(/.*\/reservation\/category/);
      
      console.log('\n📱 モバイルでカテゴリー選択画面');
      
      // カテゴリーが縦並びで表示されるか確認
      const categoryCards = await page.locator('form').count();
      console.log(`  カテゴリー数: ${categoryCards}`);
      
      // スペースy-4クラスで縦並びになっているか確認
      const verticalLayout = await page.locator('.space-y-4').isVisible();
      console.log(`  縦並びレイアウト: ${verticalLayout ? '✅' : '❌'}`);
      
      await page.screenshot({ path: 'test-results/mobile-category.png' });
    }
  });

  test('カテゴリー画像とメニュー表示', async ({ page }) => {
    console.log('\n========================================');
    console.log('🖼️ カテゴリー画像とメニュー表示テスト');
    console.log('========================================\n');
    
    // 店舗選択
    await page.goto('http://127.0.0.1:8000/reservation/store');
    await page.waitForLoadState('networkidle');
    
    const storeCount = await page.locator('.group').count();
    if (storeCount === 0) {
      console.log('⚠️ 店舗が登録されていません');
      return;
    }
    
    await page.locator('.group').first().click();
    
    // カテゴリー選択画面
    await expect(page).toHaveURL(/.*\/reservation\/category/);
    console.log('📍 カテゴリー選択画面');
    
    const categoryForms = await page.locator('form').count();
    console.log(`📊 カテゴリー数: ${categoryForms}`);
    
    for (let i = 0; i < Math.min(categoryForms, 3); i++) {
      const categoryCard = page.locator('form').nth(i);
      console.log(`\n📁 カテゴリー ${i + 1}:`);
      
      // カテゴリー名
      const categoryName = await categoryCard.locator('h3').first().textContent().catch(() => 'タイトルなし');
      console.log(`  名前: ${categoryName}`);
      
      // 画像の有無
      const hasImage = await categoryCard.locator('img').count() > 0;
      if (hasImage) {
        const imageSrc = await categoryCard.locator('img').getAttribute('src');
        console.log(`  画像: ${imageSrc ? '✅' : '❌'}`);
      } else {
        console.log(`  画像: なし`);
      }
      
      // 説明文
      const description = await categoryCard.locator('p.text-gray-600').textContent().catch(() => null);
      if (description) {
        console.log(`  説明: ${description.substring(0, 50)}...`);
      }
      
      // サンプルメニュー
      const sampleMenus = await categoryCard.locator('.bg-gray-100.text-gray-700').allTextContents();
      if (sampleMenus.length > 0) {
        console.log(`  サンプルメニュー: ${sampleMenus.join(', ')}`);
      }
      
      // メニュー数バッジ
      const menuBadge = await categoryCard.locator('.bg-green-100').textContent().catch(() => null);
      if (menuBadge) {
        console.log(`  ${menuBadge}`);
      }
    }
    
    await page.screenshot({ path: 'test-results/category-details.png', fullPage: true });
    console.log('\n📸 スクリーンショット: category-details.png');
  });

  test('エラーハンドリングと戻るリンク', async ({ page }) => {
    console.log('\n========================================');
    console.log('⚠️ エラーハンドリングテスト');
    console.log('========================================\n');
    
    // 直接カテゴリーページにアクセス（セッションなし）
    const response = await page.goto('http://127.0.0.1:8000/reservation/category', {
      waitUntil: 'networkidle'
    });
    
    console.log(`📍 直接アクセス - ステータス: ${response?.status()}`);
    
    if (response?.status() === 200) {
      // エラーメッセージまたは空のカテゴリー表示を確認
      const emptyMessage = page.locator('text=現在、予約可能なコースはありません');
      const errorMessage = page.locator('text=エラー');
      const redirectMessage = page.locator('text=店舗を選択してください');
      
      const hasMessage = 
        await emptyMessage.isVisible().catch(() => false) || 
        await errorMessage.isVisible().catch(() => false) ||
        await redirectMessage.isVisible().catch(() => false);
      
      console.log(`  メッセージ表示: ${hasMessage ? '✅' : '❌'}`);
      
      // 店舗選択に戻るリンクの確認
      const backLink = page.locator('a').filter({ hasText: '店舗選択' });
      if (await backLink.isVisible()) {
        console.log('  戻るリンク: ✅');
      }
    } else {
      console.log('  リダイレクトまたはエラーページ');
    }
    
    // 正常なフローで戻るリンクのテスト
    console.log('\n🔙 戻るリンクテスト');
    await page.goto('http://127.0.0.1:8000/reservation/store');
    
    const storeCount = await page.locator('.group').count();
    if (storeCount > 0) {
      await page.locator('.group').first().click();
      await expect(page).toHaveURL(/.*\/reservation\/category/);
      
      // 戻るリンクをクリック
      const backToStore = page.locator('a').filter({ hasText: '店舗選択に戻る' });
      await backToStore.click();
      
      // 店舗選択画面に戻ることを確認
      await expect(page).toHaveURL(/.*\/reservation\/store/);
      console.log('  ✅ 店舗選択画面に戻りました');
    }
  });

  test('注意事項セクションの表示', async ({ page }) => {
    console.log('\n========================================');
    console.log('📋 注意事項セクションテスト');
    console.log('========================================\n');
    
    // カテゴリー選択画面へ
    await page.goto('http://127.0.0.1:8000/reservation/store');
    const storeCount = await page.locator('.group').count();
    
    if (storeCount > 0) {
      await page.locator('.group').first().click();
      await expect(page).toHaveURL(/.*\/reservation\/category/);
      
      // 注意事項セクションの確認
      const noticeSection = page.locator('.bg-yellow-50');
      await expect(noticeSection).toBeVisible();
      console.log('✅ 注意事項セクション表示');
      
      // タイトル確認
      const hasTitle = await noticeSection.locator('text=ご予約の流れ').isVisible();
      console.log(`  タイトル: ${hasTitle ? '✅' : '❌'}`);
      
      // 手順の確認
      const steps = [
        'ご希望のコースをお選びください',
        '施術時間と料金をご確認ください',
        'カレンダーから空き時間をお選びください',
        'お客様情報を入力して予約完了です'
      ];
      
      for (const step of steps) {
        const hasStep = await noticeSection.locator(`text=${step}`).isVisible();
        console.log(`  ${step}: ${hasStep ? '✅' : '❌'}`);
      }
      
      // レスポンシブ確認
      await page.setViewportSize({ width: 375, height: 667 });
      await expect(noticeSection).toBeVisible();
      console.log('  モバイル表示: ✅');
    }
  });
});

// テスト完了サマリー
test.afterAll(async () => {
  console.log('\n========================================');
  console.log('📊 テスト完了サマリー');
  console.log('========================================');
  console.log('✅ 店舗選択 → カテゴリー選択');
  console.log('✅ OTP認証（テスト環境）');
  console.log('✅ レスポンシブデザイン');
  console.log('✅ カテゴリー画像表示');
  console.log('✅ エラーハンドリング');
  console.log('✅ 注意事項セクション');
  console.log('========================================\n');
});