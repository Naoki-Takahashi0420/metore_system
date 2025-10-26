const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch({ headless: false });
  const page = await browser.newPage();

  // コンソールログを監視
  page.on('console', msg => {
    if (msg.text().includes('[DEBUG]') || msg.text().includes('Chart')) {
      console.log('Browser:', msg.text());
    }
  });

  try {
    console.log('1️⃣ ログイン...');
    await page.goto('http://localhost:8000/admin/login');
    await page.fill('#data\\.email', 'naoki@yumeno-marketing.jp');
    await page.fill('#data\\.password', 'Takahashi5000');
    await page.click('button[type="submit"]');
    await page.waitForURL('http://localhost:8000/admin');
    console.log('✅ ログイン成功');

    // タイムラインウィジェットを待つ
    await page.waitForTimeout(2000);

    console.log('2️⃣ 予約を探す...');
    // タイムラインの最初の予約をクリック
    const timelineSelector = '.timeline-item, [wire\\:click*="selectReservation"], .cursor-pointer';
    const reservation = await page.locator(timelineSelector).first();
    
    if (await reservation.count() > 0) {
      console.log('✅ 予約が見つかりました');
      await reservation.click();
      await page.waitForTimeout(2000);
      
      console.log('3️⃣ カルテ履歴を開く...');
      // カルテ履歴を見るリンクを探す
      const historyLink = await page.locator('text="カルテ履歴を見る"').first();
      if (await historyLink.count() > 0) {
        await historyLink.click();
        await page.waitForTimeout(3000);
        
        console.log('4️⃣ グラフの状態を確認...');
        
        // Chart.jsが読み込まれているか
        const chartLoaded = await page.evaluate(() => typeof Chart !== 'undefined');
        console.log('Chart.js loaded:', chartLoaded);
        
        // グラフコンテナが存在するか
        const container = await page.locator('#modal-vision-chart-container');
        console.log('Container exists:', await container.count() > 0);
        
        // Canvasが存在するか
        const nakedCanvas = await page.locator('#modalNakedChart');
        console.log('Naked chart canvas exists:', await nakedCanvas.count() > 0);
        
        // タブが存在するか
        const nakedTab = await page.locator('#tab-naked');
        console.log('Naked tab exists:', await nakedTab.count() > 0);
        
        // タブ切り替えをテスト
        console.log('5️⃣ タブ切り替えテスト...');
        const correctedTab = await page.locator('#tab-corrected');
        if (await correctedTab.count() > 0) {
          await correctedTab.click();
          await page.waitForTimeout(1000);
          console.log('✅ 矯正視力タブをクリック');
        }
        
        const presbyopiaTab = await page.locator('#tab-presbyopia');
        if (await presbyopiaTab.count() > 0) {
          await presbyopiaTab.click();
          await page.waitForTimeout(1000);
          console.log('✅ 老眼測定タブをクリック');
        }
        
        // スクリーンショット
        await page.screenshot({ path: '/tmp/final-modal-chart.png', fullPage: true });
        console.log('📸 スクリーンショット: /tmp/final-modal-chart.png');
        
      } else {
        console.log('❌ カルテ履歴リンクが見つかりません');
      }
    } else {
      console.log('❌ 予約が見つかりません');
    }
    
  } catch (error) {
    console.error('❌ エラー:', error);
  }
  
  console.log('\n⏳ ブラウザを30秒間開いたままにします...');
  await page.waitForTimeout(30000);
  await browser.close();
})();