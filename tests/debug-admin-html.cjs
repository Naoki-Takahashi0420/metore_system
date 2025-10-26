const { chromium } = require('playwright');
const fs = require('fs');

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
  await page.waitForTimeout(3000);

  // HTMLをダンプ
  const html = await page.content();
  fs.writeFileSync('/tmp/admin-customer-view.html', html);
  console.log('📄 HTML saved to /tmp/admin-customer-view.html');

  // RelationManagersを探す
  const hasMedicalRecords = html.includes('医療記録') || html.includes('カルテ') || html.includes('MedicalRecords');
  console.log('🔍 Page contains medical records:', hasMedicalRecords);

  // Livewireコンポーネントを探す
  const livewireComponents = html.match(/wire:id="[^"]+"/g) || [];
  console.log('⚡ Found', livewireComponents.length, 'Livewire components');

  // タブを探す
  const tabMatches = html.match(/role="tab"/g) || [];
  console.log('📑 Found', tabMatches.length, 'tabs');

  await browser.close();
})();
