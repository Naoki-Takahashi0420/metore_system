const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch({ headless: false });
  const page = await browser.newPage();

  // コンソールログをキャプチャ
  page.on('console', msg => {
    const text = msg.text();
    if (text.includes('[DEBUG]') || text.includes('[ERROR]') || text.includes('Chart') || 
        text.includes('Modal') || text.includes('medical') || text.includes('vision')) {
      console.log('📝 Browser:', text);
    }
  });

  console.log('🔍 Logging in...');
  await page.goto('http://localhost:8000/admin/login');
  
  // ログイン
  await page.fill('#data\\.email', 'naoki@yumeno-marketing.jp');
  await page.fill('#data\\.password', 'Takahashi5000');
  await page.click('button[type="submit"]');
  await page.waitForURL('http://localhost:8000/admin');
  console.log('✅ Logged in');

  // ダッシュボードから直接予約詳細へ
  console.log('🎯 Going directly to reservation details...');
  
  // タイムラインウィジェットの最初の予約をクリック
  const firstReservation = await page.locator('.timeline-item').first();
  if (await firstReservation.count() > 0) {
    console.log('📋 Clicking first reservation in timeline...');
    await firstReservation.click();
    await page.waitForTimeout(2000);
    
    // モーダルが開くのを待つ
    const modal = await page.locator('.fi-modal').first();
    if (await modal.count() > 0) {
      console.log('✅ Modal opened');
      
      // カルテ履歴を見るリンクを探す
      const medicalLink = await page.locator('text="カルテ履歴を見る"').first();
      if (await medicalLink.count() > 0) {
        console.log('📋 Found medical history link, clicking...');
        await medicalLink.click();
        await page.waitForTimeout(3000);
        
        // グラフコンテナを確認
        const chartContainer = await page.locator('#modal-vision-chart-container');
        if (await chartContainer.count() > 0) {
          console.log('✅ Chart container found!');
          
          // Canvas要素を確認
          const canvas = await page.locator('#modalNakedVisionChart');
          if (await canvas.count() > 0) {
            console.log('✅ Canvas element found!');
            
            // Chart.jsが読み込まれているか確認
            const chartLoaded = await page.evaluate(() => typeof Chart !== 'undefined');
            console.log('Chart.js loaded:', chartLoaded);
            
            // データが渡されているか確認
            const hasData = await page.evaluate(() => {
              return typeof modalMedicalRecordsData !== 'undefined';
            });
            console.log('Data passed to JS:', hasData);
            
            // スクリーンショット
            await page.screenshot({ path: '/tmp/modal-chart.png' });
            console.log('📸 Screenshot saved to /tmp/modal-chart.png');
          } else {
            console.log('❌ Canvas element not found');
          }
        } else {
          console.log('❌ Chart container not found');
        }
      } else {
        console.log('❌ Medical history link not found');
      }
    } else {
      console.log('❌ Modal not opened');
    }
  } else {
    console.log('❌ No reservations in timeline');
  }

  console.log('\n⏳ Keeping browser open for 30 seconds...');
  await page.waitForTimeout(30000);

  await browser.close();
})();