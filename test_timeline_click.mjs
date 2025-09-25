import { chromium } from 'playwright';

const browser = await chromium.launch({ headless: false });
const page = await browser.newPage();

// コンソールメッセージを監視
page.on('console', msg => {
  console.log('Browser Console:', msg.text());
});

// エラーを監視  
page.on('pageerror', err => {
  console.error('Browser Error:', err.message);
});

try {
  // ログイン
  console.log('1. ログインページへ移動...');
  await page.goto('http://localhost:8000/admin/login');
  await page.waitForLoadState('networkidle');
  
  // ログイン
  console.log('2. ログイン実行...');
  await page.fill('input[type="email"]', 'admin@eye-training.com');
  await page.fill('input[type="password"]', 'password');
  await page.click('button[type="submit"]');
  await page.waitForLoadState('networkidle');
  
  // ダッシュボードが表示されるまで待つ
  console.log('3. ダッシュボード表示待機...');
  await page.waitForSelector('.timeline-table', { timeout: 10000 });
  
  // タイムラインテーブルの状態確認
  console.log('4. タイムライン要素を確認...');
  const timeSlots = await page.$$('.clickable-slot');
  console.log('   - クリック可能なスロット数:', timeSlots.length);
  
  // Livewireの状態確認
  const livewireStatus = await page.evaluate(() => {
    return {
      livewireExists: typeof window.Livewire !== 'undefined',
      wireElements: document.querySelectorAll('[wire\\:id]').length,
      alpineExists: typeof window.Alpine !== 'undefined', 
      xDataElements: document.querySelectorAll('[x-data]').length
    };
  });
  console.log('5. Livewire/Alpine状態:', livewireStatus);
  
  if (timeSlots.length > 0) {
    console.log('6. 最初のスロットをクリック...');
    
    // クリック前のモーダル状態
    const modalBeforeClick = await page.$('.fixed.inset-0.bg-black');
    console.log('   - クリック前モーダル:', modalBeforeClick ? '表示' : '非表示');
    
    // 最初のスロットをクリック
    await timeSlots[0].click();
    
    // 少し待つ
    await page.waitForTimeout(2000);
    
    // クリック後のモーダル状態
    const modalAfterClick = await page.$('.fixed.inset-0.bg-black');
    console.log('   - クリック後モーダル:', modalAfterClick ? '表示' : '非表示');
    
    // モーダル内のテキストを確認
    if (modalAfterClick) {
      const modalTitle = await page.textContent('h2.text-xl.font-bold');
      console.log('   - モーダルタイトル:', modalTitle);
    }
  } else {
    console.log('❌ クリック可能なスロットが見つかりません');
  }
  
  // 10秒待機してブラウザで確認できるようにする
  console.log('7. 10秒待機中...');
  await page.waitForTimeout(10000);
  
} catch (error) {
  console.error('エラー:', error);
} finally {
  await browser.close();
}
