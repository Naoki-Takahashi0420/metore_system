import { chromium } from 'playwright';

const browser = await chromium.launch({ headless: true });
const page = await browser.newPage();

console.log('🧪 Xsyumeno Production Test Started');
console.log('=====================================');

try {
  // 1. トップページアクセステスト
  console.log('\n📍 Testing: Homepage');
  await page.goto('http://54.64.54.226/', { waitUntil: 'networkidle' });
  console.log('✅ Homepage loaded');
  const title = await page.title();
  console.log(`   Title: ${title}`);
  
  // 2. 管理画面ログインページ
  console.log('\n📍 Testing: Admin Login Page');
  await page.goto('http://54.64.54.226/admin/login', { waitUntil: 'networkidle' });
  
  // CSSが読み込まれているか確認
  const cssFiles = await page.evaluate(() => {
    const styles = document.querySelectorAll('link[rel="stylesheet"]');
    return Array.from(styles).map(s => s.href);
  });
  console.log(`✅ CSS files found: ${cssFiles.length}`);
  
  // CSS各ファイルの読み込み状態を確認
  for (const cssFile of cssFiles.slice(0, 3)) {
    const response = await page.evaluate(async (url) => {
      const res = await fetch(url);
      return res.status;
    }, cssFile);
    console.log(`   ${cssFile.split('/').pop()}: ${response === 200 ? '✅' : '❌'} (${response})`);
  }
  
  // ロゴ画像の確認
  console.log('\n📍 Testing: Logo and Images');
  const images = await page.evaluate(() => {
    const imgs = document.querySelectorAll('img');
    return Array.from(imgs).map(img => ({
      src: img.src,
      alt: img.alt,
      loaded: img.complete && img.naturalHeight !== 0
    }));
  });
  
  if (images.length > 0) {
    for (const img of images) {
      console.log(`   Image: ${img.src.split('/').pop() || 'Logo'} - ${img.loaded ? '✅ Loaded' : '❌ Not loaded'}`);
    }
  } else {
    console.log('   No images found on page');
  }
  
  // スクリーンショット撮影
  await page.screenshot({ path: 'admin-login.png' });
  console.log('📸 Screenshot saved: admin-login.png');
  
  // 3. ログインテスト
  console.log('\n📍 Testing: Admin Login');
  
  // ログインフォームの確認
  const hasEmail = await page.locator('input[type="email"], input[name="email"]').count() > 0;
  const hasPassword = await page.locator('input[type="password"], input[name="password"]').count() > 0;
  
  if (hasEmail && hasPassword) {
    await page.fill('input[type="email"], input[name="email"]', 'admin@xsyumeno.com');
    await page.fill('input[type="password"], input[name="password"]', 'password');
    console.log('✅ Login form filled');
    
    // ログインボタンクリック
    await page.click('button[type="submit"]');
    console.log('✅ Login button clicked');
    
    // ログイン後の遷移を待つ
    await page.waitForTimeout(5000);
    
    // 現在のURLを確認
    const currentUrl = page.url();
    console.log(`📍 Current URL after login: ${currentUrl}`);
    
    if (currentUrl.includes('/admin') && !currentUrl.includes('/login')) {
      console.log('✅ Login successful!');
      
      // ダッシュボードのスクリーンショット
      await page.screenshot({ path: 'admin-dashboard.png' });
      console.log('📸 Dashboard screenshot: admin-dashboard.png');
      
      // ダッシュボードの要素確認
      const dashboardElements = await page.evaluate(() => {
        return {
          hasNavigation: document.querySelector('nav') !== null,
          hasSidebar: document.querySelector('aside') !== null,
          hasMainContent: document.querySelector('main') !== null
        };
      });
      console.log('\n📍 Dashboard Elements:');
      console.log(`   Navigation: ${dashboardElements.hasNavigation ? '✅' : '❌'}`);
      console.log(`   Sidebar: ${dashboardElements.hasSidebar ? '✅' : '❌'}`);
      console.log(`   Main Content: ${dashboardElements.hasMainContent ? '✅' : '❌'}`);
      
    } else {
      console.log('⚠️ Login may have failed');
      
      // エラーメッセージの確認
      const errorMessage = await page.textContent('.text-danger, .text-red-600, [role="alert"]').catch(() => null);
      if (errorMessage) {
        console.log(`   Error: ${errorMessage}`);
      }
    }
  } else {
    console.log('⚠️ Login form not found');
  }
  
  // 4. パフォーマンステスト
  console.log('\n📍 Testing: Performance');
  const metrics = await page.evaluate(() => {
    const timing = performance.timing;
    return {
      loadTime: timing.loadEventEnd - timing.navigationStart,
      domReady: timing.domContentLoadedEventEnd - timing.navigationStart,
      firstPaint: performance.getEntriesByType('paint')[0]?.startTime || 0
    };
  });
  console.log(`   Page Load Time: ${metrics.loadTime}ms`);
  console.log(`   DOM Ready: ${metrics.domReady}ms`);
  console.log(`   First Paint: ${Math.round(metrics.firstPaint)}ms`);
  
  console.log('\n=====================================');
  console.log('✅ All tests completed!');
  console.log('=====================================');
  
} catch (error) {
  console.error('❌ Test failed:', error.message);
  console.error(error.stack);
} finally {
  await browser.close();
}