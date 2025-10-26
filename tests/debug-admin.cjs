const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch({ headless: false });
  const page = await browser.newPage();

  console.log('🔍 Navigating to admin login...');
  await page.goto('http://localhost:8000/admin/login');

  // ログイン
  console.log('🔑 Logging in...');
  await page.fill('input[type="email"]', 'admin@eye-training.com');
  await page.fill('input[type="password"]', 'password');
  await page.click('button[type="submit"]');

  await page.waitForLoadState('networkidle');
  console.log('✅ Logged in successfully');

  // 顧客管理に移動
  console.log('👥 Navigating to customer 3673...');
  await page.goto('http://localhost:8000/admin/customers/3673');
  await page.waitForLoadState('networkidle');

  // コンソールログをキャプチャ
  page.on('console', msg => {
    if (msg.text().includes('[DEBUG]') || msg.text().includes('[ERROR]')) {
      console.log('📝 Console:', msg.text());
    }
  });

  // カルテタブをクリック
  console.log('📋 Looking for Medical Records tab...');
  await page.waitForTimeout(2000);

  // タブの存在確認
  const tabs = await page.$$('[role="tab"]');
  console.log(`📊 Found ${tabs.length} tabs`);

  for (let i = 0; i < tabs.length; i++) {
    const text = await tabs[i].textContent();
    console.log(`  Tab ${i}: ${text}`);
    if (text.includes('カルテ')) {
      console.log('🎯 Found Medical Records tab, clicking...');
      await tabs[i].click();
      await page.waitForTimeout(2000);
      break;
    }
  }

  // グラフコンテナの存在確認
  const chartContainer = await page.$('#admin-vision-chart-container');
  if (chartContainer) {
    const isHidden = await chartContainer.evaluate(el => el.classList.contains('hidden'));
    console.log('📈 Chart container found! Hidden:', isHidden);
  } else {
    console.log('❌ Chart container not found');
  }

  // ページのHTMLを確認
  const html = await page.content();
  const hasChartDiv = html.includes('admin-vision-chart-container');
  console.log('🔍 HTML contains chart div:', hasChartDiv);

  // スクリーンショット
  await page.screenshot({ path: '/tmp/admin-customer-view.png', fullPage: true });
  console.log('📸 Screenshot saved to /tmp/admin-customer-view.png');

  console.log('\n⏳ Keeping browser open for 30 seconds...');
  await page.waitForTimeout(30000);

  await browser.close();
})();
