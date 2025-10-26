const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch({ headless: false });
  const page = await browser.newPage();

  // コンソールログをキャプチャ
  page.on('console', msg => {
    const text = msg.text();
    if (text.includes('[DEBUG]') || text.includes('[ERROR]') || text.includes('Chart')) {
      console.log('📝 Browser console:', text);
    }
  });

  console.log('🔍 Navigating to admin login...');
  await page.goto('http://localhost:8000/admin/login');

  // 正しいアカウントでログイン
  console.log('🔑 Logging in with naoki@yumeno-marketing.jp...');
  await page.fill('input[type="email"]', 'naoki@yumeno-marketing.jp');
  await page.fill('input[type="password"]', 'Takahashi5000');
  await page.click('button[type="submit"]');

  await page.waitForLoadState('networkidle');
  console.log('✅ Logged in to:', page.url());

  // 顧客管理に移動
  console.log('\n🎯 Going to /admin/customers...');
  await page.goto('http://localhost:8000/admin/customers');
  await page.waitForLoadState('networkidle');
  console.log('📍 Current URL:', page.url());

  await page.waitForTimeout(2000);

  // 高橋直希を検索
  console.log('\n🔍 Searching for 高橋直希...');
  const searchInput = await page.$('input[placeholder*="検索"], input[type="search"]');
  if (searchInput) {
    await searchInput.fill('高橋直希');
    await page.waitForTimeout(1000);
  }

  // 顧客をクリック
  const customerLink = await page.$('a:has-text("高橋")');
  if (customerLink) {
    console.log('🎯 Found customer link, clicking...');
    await customerLink.click();
    await page.waitForLoadState('networkidle');
    console.log('📍 Customer view URL:', page.url());
  } else {
    console.log('❌ Customer link not found, trying direct URL...');
    await page.goto('http://localhost:8000/admin/customers/3673');
    await page.waitForLoadState('networkidle');
  }

  await page.waitForTimeout(3000);

  // タブを確認
  console.log('\n📑 Checking tabs...');
  const tabs = await page.$$('[role="tab"]');
  console.log(`Found ${tabs.length} tabs`);
  for (let i = 0; i < tabs.length; i++) {
    const text = await tabs[i].textContent();
    console.log(`  Tab ${i}: ${text}`);
  }

  // カルテタブをクリック
  for (const tab of tabs) {
    const text = await tab.textContent();
    if (text && text.includes('カルテ')) {
      console.log('\n🎯 Clicking カルテ tab...');
      await tab.click();
      await page.waitForTimeout(3000);
      break;
    }
  }

  // グラフを確認
  console.log('\n📊 Checking for chart container...');
  const chartContainer = await page.$('#admin-vision-chart-container');
  if (chartContainer) {
    const isHidden = await chartContainer.evaluate(el => el.classList.contains('hidden'));
    console.log('✅ Chart container found! Hidden:', isHidden);

    const canvases = await page.$$('canvas');
    console.log('🎨 Found', canvases.length, 'canvas elements');
  } else {
    console.log('❌ Chart container not found');
  }

  // HTMLを確認
  const html = await page.content();
  const hasChartDiv = html.includes('admin-vision-chart-container');
  console.log('📄 HTML contains chart div:', hasChartDiv);

  console.log('\n⏳ Keeping browser open for 60 seconds...');
  await page.waitForTimeout(60000);

  await browser.close();
})();
