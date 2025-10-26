const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch({ headless: false });
  const page = await browser.newPage();

  // コンソールログをキャプチャ
  page.on('console', msg => {
    const text = msg.text();
    console.log('📝 Browser:', text);
  });

  console.log('🔍 Logging in...');
  await page.goto('http://localhost:8000/admin/login');
  
  // ログイン
  await page.fill('#data\\.email', 'naoki@yumeno-marketing.jp');
  await page.fill('#data\\.password', 'Takahashi5000');
  await page.click('button[type="submit"]');
  await page.waitForURL('http://localhost:8000/admin');
  console.log('✅ Logged in');

  // 顧客管理ページへ
  console.log('🎯 Going to customers page...');
  await page.goto('http://localhost:8000/admin/customers');
  await page.waitForTimeout(2000);
  
  // 最初の顧客をクリック
  const firstCustomer = await page.locator('table tbody tr').first().locator('a').first();
  if (await firstCustomer.count() > 0) {
    console.log('📋 Clicking first customer...');
    await firstCustomer.click();
    await page.waitForTimeout(3000);
    
    // URLを確認
    const currentUrl = page.url();
    console.log('Current URL:', currentUrl);
    
    // カルテ履歴タブを探す
    const medicalTab = await page.locator('text="カルテ履歴"').first();
    if (await medicalTab.count() > 0) {
      console.log('📋 Found medical history tab, clicking...');
      await medicalTab.click();
      await page.waitForTimeout(3000);
      
      // グラフコンテナを確認
      const chartContainer = await page.locator('#modal-vision-chart-container');
      if (await chartContainer.count() > 0) {
        console.log('✅ Chart container found!');
        
        // Canvas要素を確認
        const nakedCanvas = await page.locator('#modalNakedVisionChart');
        const correctedCanvas = await page.locator('#modalCorrectedVisionChart');
        const presbyopiaCanvas = await page.locator('#modalPresbyopiaVisionChart');
        
        console.log('Canvas elements:');
        console.log('  Naked vision canvas:', await nakedCanvas.count() > 0);
        console.log('  Corrected vision canvas:', await correctedCanvas.count() > 0);
        console.log('  Presbyopia canvas:', await presbyopiaCanvas.count() > 0);
        
        // Chart.jsが読み込まれているか確認
        const chartStatus = await page.evaluate(() => {
          return {
            chartJsLoaded: typeof Chart !== 'undefined',
            dataExists: typeof modalMedicalRecordsData !== 'undefined',
            dataLength: typeof modalMedicalRecordsData !== 'undefined' ? modalMedicalRecordsData.length : 0
          };
        });
        console.log('Chart status:', chartStatus);
        
        // スクリーンショット
        await page.screenshot({ path: '/tmp/customer-chart.png', fullPage: true });
        console.log('📸 Screenshot saved to /tmp/customer-chart.png');
      } else {
        console.log('❌ Chart container not found');
        
        // ページのHTMLを確認
        const pageContent = await page.content();
        if (pageContent.includes('視力推移グラフ')) {
          console.log('✓ Graph title found in HTML');
        } else {
          console.log('✗ Graph title NOT found in HTML');
        }
      }
    } else {
      console.log('❌ Medical history tab not found');
      
      // 利用可能なタブを表示
      const tabs = await page.locator('[role="tab"]').allTextContents();
      console.log('Available tabs:', tabs);
    }
  } else {
    console.log('❌ No customers found');
  }

  console.log('\n⏳ Keeping browser open for 30 seconds...');
  await page.waitForTimeout(30000);

  await browser.close();
})();