const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch({ headless: false });
  const page = await browser.newPage();

  await page.goto('http://localhost:8000/admin/login');
  await page.fill('#data\\.email', 'naoki@yumeno-marketing.jp');
  await page.fill('#data\\.password', 'Takahashi5000');
  await page.click('button[type="submit"]');
  await page.waitForURL('http://localhost:8000/admin');
  
  // 10月24日の高岸 玲奈さんの予約を探す
  await page.waitForTimeout(3000);
  
  // 予約をクリック
  const reservation = await page.getByText('高岸 玲奈').first();
  if (await reservation.count() > 0) {
    console.log('✅ 高岸さんの予約を見つけました');
    await reservation.click();
    await page.waitForTimeout(3000);
    
    // カルテ履歴を見る をクリック
    const link = await page.getByText('カルテ履歴を見る').first();
    if (await link.count() > 0) {
      console.log('✅ カルテ履歴リンクをクリック');
      await link.click();
      await page.waitForTimeout(3000);
      
      // グラフが表示されるか確認
      const chart = await page.locator('#modal-vision-chart-container');
      console.log('グラフコンテナ:', await chart.count() > 0 ? '表示' : '非表示');
      
      // タブをクリックしてみる
      const tab = await page.locator('#tab-corrected');
      if (await tab.count() > 0) {
        await tab.click();
        console.log('✅ 矯正視力タブをクリック');
      }
      
      await page.screenshot({ path: '/tmp/final-chart.png' });
      console.log('📸 /tmp/final-chart.png');
    }
  }
  
  await page.waitForTimeout(30000);
  await browser.close();
})();