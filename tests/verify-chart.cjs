const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch({ headless: false });
  const page = await browser.newPage();

  page.on('console', msg => {
    console.log('Console:', msg.text());
  });

  console.log('ログイン中...');
  await page.goto('http://localhost:8000/admin/login');
  await page.fill('#data\\.email', 'naoki@yumeno-marketing.jp');
  await page.fill('#data\\.password', 'Takahashi5000');
  await page.click('button[type="submit"]');
  await page.waitForURL('http://localhost:8000/admin');

  // 予約詳細を直接開く方法を試す
  console.log('予約を探す...');
  await page.waitForTimeout(3000);
  
  // タイムラインの予約項目を見つける
  const reservations = await page.locator('.bg-blue-50, .bg-green-50, .bg-yellow-50, .bg-red-50').all();
  console.log('予約数:', reservations.length);
  
  if (reservations.length > 0) {
    await reservations[0].click();
    await page.waitForTimeout(2000);
    
    // モーダルが開いたか確認
    const modal = await page.locator('.fixed.inset-0.z-50').first();
    if (await modal.count() > 0) {
      console.log('モーダルが開きました');
      
      // カルテ履歴を見るリンクを探す - テキストで検索
      const link = await page.getByText('カルテ履歴を見る').first();
      const linkCount = await link.count();
      console.log('カルテ履歴リンク数:', linkCount);
      
      if (linkCount > 0) {
        console.log('カルテ履歴リンクをクリック');
        await link.click();
        await page.waitForTimeout(3000);
        
        // グラフが表示されているか確認
        const chartContainer = await page.locator('#modal-vision-chart-container');
        console.log('グラフコンテナ:', await chartContainer.count() > 0);
        
        // Chart.jsの状態確認
        const chartStatus = await page.evaluate(() => {
          return {
            chartLoaded: typeof Chart !== 'undefined',
            canvas: document.getElementById('modalNakedChart') !== null,
            switchFunction: typeof switchVisionTab !== 'undefined'
          };
        });
        console.log('Chart状態:', chartStatus);
        
        // スクリーンショット
        await page.screenshot({ path: '/tmp/chart-debug.png', fullPage: true });
        console.log('スクリーンショット保存: /tmp/chart-debug.png');
        
        // HTMLの一部を取得
        const html = await chartContainer.innerHTML();
        console.log('HTML:', html.substring(0, 500));
      } else {
        console.log('カルテ履歴リンクが見つかりません');
        
        // モーダルの内容を確認
        const modalContent = await page.locator('.fixed.inset-0.z-50.overflow-y-auto').innerHTML();
        console.log('モーダル内にある文字列:');
        if (modalContent.includes('カルテ情報')) console.log('- カルテ情報');
        if (modalContent.includes('カルテ履歴')) console.log('- カルテ履歴');
        
        // スクリーンショット保存
        await page.screenshot({ path: '/tmp/modal-content.png', fullPage: true });
        console.log('スクリーンショット: /tmp/modal-content.png');
      }
    } else {
      console.log('モーダルが開きません');
    }
  }
  
  await page.waitForTimeout(10000);
  await browser.close();
})();