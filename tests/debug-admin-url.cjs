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
  console.log('📍 Current URL:', page.url());

  // 顧客一覧に移動
  console.log('\n👥 Looking for Customers navigation...');
  await page.waitForTimeout(2000);

  // ナビゲーションメニューをクリック
  const navLinks = await page.$$('a');
  for (const link of navLinks) {
    const text = await link.textContent();
    if (text && text.includes('顧客')) {
      console.log('🎯 Found customer link:', text);
      await link.click();
      await page.waitForLoadState('networkidle');
      console.log('📍 After clicking:', page.url());
      break;
    }
  }

  await page.waitForTimeout(2000);

  // テーブル内の最初の顧客をクリック
  console.log('\n🔍 Looking for customer 3673 in table...');
  const rows = await page.$$('tr');
  console.log(`📊 Found ${rows.length} table rows`);

  for (let i = 0; i < rows.length; i++) {
    const text = await rows[i].textContent();
    if (text && text.includes('高橋')) {
      console.log(`🎯 Found customer row ${i}:`, text.substring(0, 100));
      const links = await rows[i].$$('a');
      if (links.length > 0) {
        await links[0].click();
        await page.waitForLoadState('networkidle');
        console.log('📍 Customer view URL:', page.url());
        break;
      }
    }
  }

  await page.waitForTimeout(3000);

  // ページの構造を確認
  console.log('\n🔍 Checking page structure...');
  const tabsFound = await page.$$('[role="tab"]');
  console.log(`📑 Tabs found: ${tabsFound.length}`);

  for (let i = 0; i < tabsFound.length; i++) {
    const text = await tabsFound[i].textContent();
    console.log(`  Tab ${i}: ${text}`);
  }

  await page.waitForTimeout(30000);
  await browser.close();
})();
