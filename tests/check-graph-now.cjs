const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch({ headless: false });
  const page = await browser.newPage();
  
  page.on('console', msg => {
    console.log('Browser:', msg.text());
  });

  // ログイン
  await page.goto('http://localhost:8000/admin/login');
  await page.fill('#data\\.email', 'naoki@yumeno-marketing.jp');
  await page.fill('#data\\.password', 'Takahashi5000');
  await page.click('button[type="submit"]');
  await page.waitForURL('http://localhost:8000/admin');
  
  // タイムラインを待つ
  await page.waitForTimeout(3000);
  
  // 特定の顧客名をクリック
  const customerName = await page.locator('text="うえだ あつこ"').first();
  if (await customerName.count() > 0) {
    console.log('Found customer: うえだ あつこ');
    await customerName.click();
    console.log('Clicked customer');
    await page.waitForTimeout(3000);
    
    // モーダルが開いたか確認
    const modalVisible = await page.locator('.fixed.inset-0.z-50').count();
    console.log(`Modal visible: ${modalVisible > 0}`);
    
    if (modalVisible > 0) {
      // カルテ履歴リンクを探す - 複数の方法で試す
      const selectors = [
        'text="カルテ履歴を見る"',
        'button:has-text("カルテ履歴")',
        '[wire\\:click*="showMedicalHistoryModal"]',
        'text=/カルテ履歴/'
      ];
      
      let found = false;
      for (const selector of selectors) {
        const element = await page.locator(selector).first();
        if (await element.count() > 0) {
          console.log(`Found with selector: ${selector}`);
          await element.click();
          found = true;
          break;
        }
      }
      
      if (!found) {
        console.log('Medical history link not found');
        // モーダル内のテキストを取得 - 最初のモーダル内容を取得
        const modalText = await page.locator('.fixed.inset-0.z-50').last().textContent();
        console.log('Modal contains "カルテ":', modalText.includes('カルテ'));
        
        // スクリーンショット
        await page.screenshot({ path: '/tmp/modal-debug.png' });
        console.log('Screenshot: /tmp/modal-debug.png');
      } else {
        // カルテ履歴モーダルが開いた
        await page.waitForTimeout(3000);
        
        // グラフ関連要素を確認
        const chartContainer = await page.locator('#modal-vision-chart-container').count();
        const canvas = await page.locator('#modalSimpleChart').count();
        const testButton = await page.locator('button:has-text("グラフをテスト表示")').count();
        
        console.log(`Chart container: ${chartContainer > 0}`);
        console.log(`Canvas: ${canvas > 0}`);
        console.log(`Test button: ${testButton > 0}`);
        
        // テストボタンがあればクリック
        if (testButton > 0) {
          await page.locator('button:has-text("グラフをテスト表示")').click();
          console.log('Clicked test button');
          await page.waitForTimeout(2000);
        }
        
        // Chart.jsの状態確認
        const chartStatus = await page.evaluate(() => ({
          chartLoaded: typeof Chart !== 'undefined',
          canvasExists: document.getElementById('modalSimpleChart') !== null,
          testFunction: typeof testChart === 'function'
        }));
        console.log('Chart status:', chartStatus);
        
        // testChart関数を直接実行
        if (chartStatus.testFunction) {
          await page.evaluate(() => testChart());
          console.log('Called testChart()');
        }
        
        await page.screenshot({ path: '/tmp/graph-result.png' });
        console.log('Screenshot: /tmp/graph-result.png');
      }
    }
  } else {
    console.log('Customer not found');
  }
  
  await page.waitForTimeout(5000);
  await browser.close();
})();