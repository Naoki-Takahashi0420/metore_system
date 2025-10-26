const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch({ headless: false });
  const page = await browser.newPage();

  // コンソールログをキャプチャ
  page.on('console', msg => {
    console.log('📝 Browser:', msg.text());
  });

  console.log('🔍 Logging in...');
  await page.goto('http://localhost:8000/admin/login');
  await page.fill('input[type="email"]', 'naoki@yumeno-marketing.jp');
  await page.fill('input[type="password"]', 'Takahashi5000');
  await page.click('button[type="submit"]');
  await page.waitForLoadState('networkidle');
  console.log('✅ Logged in');

  console.log('\n🎯 Opening reservation timeline...');
  await page.goto('http://localhost:8000/admin');
  await page.waitForLoadState('networkidle');
  await page.waitForTimeout(2000);

  // 予約をクリック
  console.log('🔍 Looking for reservation...');
  const reservation = await page.$('.fc-event, [class*="reservation"]');
  if (reservation) {
    await reservation.click();
    await page.waitForTimeout(2000);
  }

  // カルテ履歴リンクを探す
  console.log('📋 Looking for medical history link...');
  const medicalHistoryLink = await page.$('text=カルテ履歴を見る');
  if (medicalHistoryLink) {
    console.log('🎯 Found link, clicking...');
    await medicalHistoryLink.click();
    await page.waitForTimeout(3000);

    // グラフコンテナを確認
    const chartContainer = await page.$('#modal-vision-chart-container');
    console.log('📊 Chart container found:', !!chartContainer);

    // Canvas要素を確認
    const canvas = await page.$('#modalNakedVisionChart');
    console.log('🎨 Canvas found:', !!canvas);

    // Chart.jsが読み込まれているか確認
    const hasChart = await page.evaluate(() => typeof Chart !== 'undefined');
    console.log('📈 Chart.js loaded:', hasChart);

    // データを確認
    const hasData = await page.evaluate(() => typeof modalMedicalRecordsData !== 'undefined');
    console.log('📦 Data loaded:', hasData);

    if (hasData) {
      const dataLength = await page.evaluate(() => modalMedicalRecordsData?.length);
      console.log('📊 Data length:', dataLength);
    }

    // スクリーンショット
    await page.screenshot({ path: '/tmp/modal-chart.png', fullPage: true });
    console.log('📸 Screenshot saved');

  } else {
    console.log('❌ Medical history link not found');
  }

  console.log('\n⏳ Keeping browser open for 30 seconds...');
  await page.waitForTimeout(30000);

  await browser.close();
})();
