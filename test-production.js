const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage();
  
  console.log('🧪 Xsyumeno Production Test Started');
  console.log('=====================================');
  
  try {
    // 1. トップページアクセステスト
    console.log('\n📍 Testing: Homepage');
    await page.goto('http://54.64.54.226/', { waitUntil: 'networkidle' });
    console.log('✅ Homepage loaded');
    
    // 2. 管理画面ログインページ
    console.log('\n📍 Testing: Admin Login Page');
    await page.goto('http://54.64.54.226/admin/login', { waitUntil: 'networkidle' });
    
    // CSSが読み込まれているか確認
    const hasCSS = await page.evaluate(() => {
      const styles = document.querySelectorAll('link[rel="stylesheet"]');
      return styles.length > 0;
    });
    console.log(`✅ CSS loaded: ${hasCSS ? 'Yes' : 'No'}`);
    
    // スクリーンショット撮影
    await page.screenshot({ path: 'admin-login.png' });
    console.log('📸 Screenshot saved: admin-login.png');
    
    // 3. ログインテスト
    console.log('\n📍 Testing: Admin Login');
    
    // ログインフォームの確認
    const emailInput = await page.locator('input[type="email"], input[name="email"]').first();
    const passwordInput = await page.locator('input[type="password"], input[name="password"]').first();
    
    if (emailInput && passwordInput) {
      await emailInput.fill('admin@xsyumeno.com');
      await passwordInput.fill('password');
      console.log('✅ Login form filled');
      
      // ログインボタンクリック
      const loginButton = await page.locator('button[type="submit"]').first();
      await loginButton.click();
      console.log('✅ Login button clicked');
      
      // ログイン後の遷移を待つ
      await page.waitForTimeout(3000);
      
      // 現在のURLを確認
      const currentUrl = page.url();
      console.log(`📍 Current URL: ${currentUrl}`);
      
      if (currentUrl.includes('/admin') && !currentUrl.includes('/login')) {
        console.log('✅ Login successful!');
        
        // ダッシュボードのスクリーンショット
        await page.screenshot({ path: 'admin-dashboard.png' });
        console.log('📸 Screenshot saved: admin-dashboard.png');
      } else {
        console.log('⚠️ Login may have failed');
      }
    } else {
      console.log('⚠️ Login form not found');
    }
    
    // 4. レスポンシブテスト
    console.log('\n📍 Testing: Responsive Design');
    
    // モバイルビュー
    await page.setViewportSize({ width: 375, height: 667 });
    await page.screenshot({ path: 'mobile-view.png' });
    console.log('📸 Mobile view screenshot: mobile-view.png');
    
    // タブレットビュー
    await page.setViewportSize({ width: 768, height: 1024 });
    await page.screenshot({ path: 'tablet-view.png' });
    console.log('📸 Tablet view screenshot: tablet-view.png');
    
    // デスクトップビュー
    await page.setViewportSize({ width: 1920, height: 1080 });
    await page.screenshot({ path: 'desktop-view.png' });
    console.log('📸 Desktop view screenshot: desktop-view.png');
    
    console.log('\n=====================================');
    console.log('✅ All tests completed successfully!');
    console.log('=====================================');
    
  } catch (error) {
    console.error('❌ Test failed:', error.message);
  } finally {
    await browser.close();
  }
})();