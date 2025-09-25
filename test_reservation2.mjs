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
    console.log('5. 10番目の空きスロットをクリック...');
    await emptySlots[10].click();

    // モーダルが開くのを待つ
    await page.waitForTimeout(1000);

    // モーダルが表示されているか確認
    const modalVisible = await page.$('.fixed.inset-0');
    console.log('   モーダル表示:', modalVisible ? 'はい' : 'いいえ');

    if (modalVisible) {
      // 電話番号で検索 - 既存顧客の番号を使用
      console.log('6. 電話番号で顧客検索...');
      const phoneInput = await page.$('input[placeholder*="電話番号"]');
      if (phoneInput) {
        await phoneInput.fill('0205555555');  // 田中さんの番号
        await page.waitForTimeout(1500);

        // 検索結果から最初の顧客を選択
        const searchResults = await page.$$('.bg-gray-50');
        console.log('   検索結果数:', searchResults.length);

        if (searchResults.length > 0) {
          await searchResults[0].click();
          console.log('   顧客を選択しました');

          // 次へボタンをクリック
          await page.waitForTimeout(500);
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
            await page.waitForTimeout(500);
            const createButton = await page.$('button:has-text("予約作成")');
            if (createButton) {
              console.log('8. 予約作成ボタンをクリック...');
              await createButton.click();
              await page.waitForTimeout(3000);

              // エラーメッセージを確認
              const errorMessage = await page.$('.text-red-600');
              if (errorMessage) {
                const errorText = await errorMessage.textContent();
                console.log('   エラー:', errorText);
              } else {
                console.log('   予約作成成功！');
              }
            }
          }
        } else {
          console.log('   顧客が見つかりません');
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