const { chromium } = require('playwright');

(async () => {
  console.log('🚀 サブスクリプション管理機能テスト開始...');
  
  const browser = await chromium.launch({ 
    headless: false,
    slowMo: 1000 
  });
  
  const context = await browser.newContext();
  const page = await context.newPage();

  try {
    // 1. 管理画面ログイン
    console.log('📝 管理画面にログイン...');
    await page.goto('http://localhost:8002/admin/login');
    await page.fill('input[name="email"]', 'admin@eye-training.com');
    await page.fill('input[name="password"]', 'password');
    await page.click('button[type="submit"]');
    await page.waitForURL('**/admin', { timeout: 10000 });
    console.log('✅ ログイン成功');

    // 2. ダッシュボードで要対応顧客ウィジェット確認
    console.log('📊 要対応顧客ウィジェット確認...');
    const attentionWidget = await page.locator('text=要対応顧客').first();
    if (await attentionWidget.isVisible()) {
      console.log('✅ 要対応顧客ウィジェットが表示されています');
      
      // 高橋直希様の決済失敗表示確認
      const takahashiRow = await page.locator('text=高橋 直希').first();
      if (await takahashiRow.isVisible()) {
        console.log('✅ 高橋直希様が要対応顧客として表示されています');
      }
    } else {
      console.log('❌ 要対応顧客ウィジェットが見つかりません');
    }

    // 3. サブスク契約管理ページへ移動
    console.log('🔄 サブスク契約管理ページに移動...');
    await page.goto('http://localhost:8002/admin/customer-subscriptions');
    await page.waitForLoadState('networkidle');
    
    const pageTitle = await page.locator('h1').first();
    if (await pageTitle.isVisible()) {
      console.log('✅ サブスク契約管理ページに到着');
      
      // 高橋直希様のレコード確認
      const takahashiRecord = await page.locator('text=高橋 直希').first();
      if (await takahashiRecord.isVisible()) {
        console.log('✅ 高橋直希様のサブスクレコードが表示されています');
        
        // 決済失敗バッジの確認
        const failureBadge = await page.locator('text=🔴 決済失敗').first();
        if (await failureBadge.isVisible()) {
          console.log('✅ 決済失敗バッジが正しく表示されています');
        }
      }
    } else {
      console.log('❌ サブスク契約管理ページの読み込みに失敗');
    }

    console.log('🎉 テスト完了！3秒後にブラウザを閉じます...');
    await page.waitForTimeout(3000);

  } catch (error) {
    console.error('❌ テスト中にエラーが発生:', error.message);
  } finally {
    await browser.close();
  }
})();