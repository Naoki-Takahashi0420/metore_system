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
  console.log('✅ Logged in to:', page.url());

  await page.waitForTimeout(2000);

  // 全てのナビゲーションリンクを表示
  console.log('\n📋 All navigation items:');
  const navItems = await page.$$('[class*="navigation"], nav a, aside a, [role="navigation"] a');
  for (let i = 0; i < navItems.length; i++) {
    const text = await navItems[i].textContent();
    const href = await navItems[i].getAttribute('href');
    if (text && text.trim()) {
      console.log(`  ${i}: "${text.trim()}" → ${href}`);
    }
  }

  // 直接URLで顧客管理に行ってみる
  console.log('\n🎯 Trying direct URL: /admin/customers');
  await page.goto('http://localhost:8000/admin/customers');
  await page.waitForLoadState('networkidle');
  console.log('📍 Current URL:', page.url());

  const pageTitle = await page.title();
  console.log('📄 Page title:', pageTitle);

  await page.waitForTimeout(30000);
  await browser.close();
})();
