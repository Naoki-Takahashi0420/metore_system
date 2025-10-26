const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch({ headless: false });
  const page = await browser.newPage();

  page.on('console', msg => {
    if (msg.text().includes('[DEBUG]')) {
      console.log('Browser:', msg.text());
    }
  });

  await page.goto('http://localhost:8000/admin/login');
  await page.fill('#data\\.email', 'naoki@yumeno-marketing.jp');
  await page.fill('#data\\.password', 'Takahashi5000');
  await page.click('button[type="submit"]');
  await page.waitForURL('http://localhost:8000/admin');
  console.log('✅ Logged in');
  
  await page.waitForTimeout(3000);
  
  // 予約をクリック
  const reservation = await page.locator('.bg-blue-50, .bg-green-50').first();
  if (await reservation.count() > 0) {
    await reservation.click();
    console.log('✅ Reservation clicked');
    await page.waitForTimeout(2000);
    
    // スクリーンショットを撮る
    await page.screenshot({ path: '/tmp/modal-state.png', fullPage: true });
    console.log('📸 Modal state: /tmp/modal-state.png');
    
    // カルテ履歴リンクを探す
    const link = await page.getByText('カルテ履歴を見る').first();
    if (await link.count() > 0) {
      await link.click();
      console.log('✅ Medical history link clicked');
      await page.waitForTimeout(3000);
      
      // グラフのキャンバスを確認
      const canvas = await page.locator('#modalSimpleChart');
      console.log('Canvas exists:', await canvas.count() > 0);
      
      // Chart.jsの状態
      const chartStatus = await page.evaluate(() => {
        return {
          chartLoaded: typeof Chart !== 'undefined',
          canvasFound: document.getElementById('modalSimpleChart') !== null
        };
      });
      console.log('Chart status:', chartStatus);
      
      // 手動でグラフ描画を試す
      await page.evaluate(() => {
        if (typeof window.drawMedicalHistoryChart === 'function') {
          window.drawMedicalHistoryChart();
        }
      });
      
      await page.waitForTimeout(2000);
      await page.screenshot({ path: '/tmp/simple-chart-test.png', fullPage: true });
      console.log('📸 Screenshot: /tmp/simple-chart-test.png');
    } else {
      console.log('❌ Medical history link not found');
    }
  }
  
  await page.waitForTimeout(30000);
  await browser.close();
})();