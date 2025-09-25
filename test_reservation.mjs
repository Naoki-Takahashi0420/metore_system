import { chromium } from 'playwright';

const browser = await chromium.launch({ headless: false });
const page = await browser.newPage();

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

  // 空いているスロットを探す
  console.log('4. 空いているスロットを探す...');
  const emptySlots = await page.$$('.clickable-slot:not(.has-reservation)');
  console.log('   空いているスロット数:', emptySlots.length);

  if (emptySlots.length > 0) {
    console.log('5. 最初の空きスロットをクリック...');
    await emptySlots[0].click();

    // モーダルが開くのを待つ
    await page.waitForTimeout(1000);

    // モーダルが表示されているか確認
    const modalVisible = await page.$('.fixed.inset-0');
    console.log('   モーダル表示:', modalVisible ? 'はい' : 'いいえ');

    if (modalVisible) {
      // 電話番号で検索
      console.log('6. 電話番号で顧客検索...');
      const phoneInput = await page.$('input[placeholder*="電話番号"]');
      if (phoneInput) {
        await phoneInput.fill('09012345678');
        await page.waitForTimeout(1000);

        // 検索結果から最初の顧客を選択
        const searchResult = await page.$('.bg-gray-50.hover\\:bg-gray-100');
        if (searchResult) {
          await searchResult.click();
          console.log('   顧客を選択しました');
        } else {
          console.log('   既存顧客が見つかりません');
        }
      }

      // 次へボタンをクリック
      const nextButton = await page.$('button:has-text("次へ")');
      if (nextButton) {
        await nextButton.click();
        console.log('7. 次のステップへ...');
        await page.waitForTimeout(1000);

        // メニューを選択
        const menuSelect = await page.$('select[id*="menu_id"]');
        if (menuSelect) {
          const options = await menuSelect.$$eval('option', opts => opts.map(opt => opt.value).filter(v => v));
          if (options.length > 0) {
            await menuSelect.selectOption(options[0]);
            console.log('   メニューを選択しました');
          }
        }

        // 予約作成ボタンをクリック
        const createButton = await page.$('button:has-text("予約作成")');
        if (createButton) {
          console.log('8. 予約作成ボタンをクリック...');
          await createButton.click();
          await page.waitForTimeout(2000);
        }
      }
    }
  }

  console.log('9. 5秒待機中...');
  await page.waitForTimeout(5000);

} catch (error) {
  console.error('エラー:', error);
} finally {
  await browser.close();
}