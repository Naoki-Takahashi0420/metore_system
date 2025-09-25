import { chromium } from 'playwright';

const browser = await chromium.launch({ headless: false });
const page = await browser.newPage();

try {
  // ログイン
  console.log('1. ログインページへ移動...');
  await page.goto('http://localhost:8002/admin/login');
  await page.waitForLoadState('networkidle');

  // ログイン
  console.log('2. ログイン実行...');
  await page.fill('input[type="email"]', 'admin@eye-training.com');
  await page.fill('input[type="password"]', 'password');
  await page.click('button[type="submit"]');
  await page.waitForLoadState('networkidle');

  // 店舗設定ページへ移動
  console.log('3. 店舗設定ページへ移動...');
  await page.goto('http://localhost:8002/admin/stores/1/edit');
  await page.waitForLoadState('networkidle');

  // 予約スロット時間の設定を確認
  console.log('4. 予約スロット時間の設定を確認...');
  const slotDurationSelect = await page.$('select[name="data.reservation_slot_duration"]');
  if (slotDurationSelect) {
    const currentValue = await slotDurationSelect.evaluate(el => el.value);
    console.log('   現在の設定値:', currentValue, '分');

    // 30分に変更してみる
    console.log('5. 30分に変更...');
    await slotDurationSelect.selectOption('30');

    // 保存ボタンをクリック
    const saveButton = await page.$('button[type="submit"]');
    if (saveButton) {
      await saveButton.click();
      await page.waitForTimeout(2000);
      console.log('   ✅ 保存成功');
    }
  } else {
    console.log('   ❌ 予約スロット時間の設定フィールドが見つかりません');
  }

  // ダッシュボードに戻って確認
  console.log('6. ダッシュボードで確認...');
  await page.goto('http://localhost:8002/admin');
  await page.waitForSelector('.timeline-table', { timeout: 10000 });

  // タイムラインのスロットを確認
  const timeHeaders = await page.$$eval('.timeline-table thead th[colspan]', elements => {
    return elements.map(el => el.textContent.trim()).filter(t => t.includes(':'));
  });

  console.log('7. タイムラインの時間スロット:');
  console.log('   ', timeHeaders.slice(0, 10).join(', '), '...');

  // 左列の可視性を確認
  const leftColumn = await page.$('.seat-label');
  if (leftColumn) {
    const styles = await leftColumn.evaluate(el => {
      const computed = window.getComputedStyle(el);
      return {
        minWidth: computed.minWidth,
        padding: computed.padding,
        fontWeight: computed.fontWeight,
        position: computed.position,
        zIndex: computed.zIndex
      };
    });
    console.log('8. 左列のスタイル:', styles);
  }

  console.log('9. 10秒待機中...');
  await page.waitForTimeout(10000);

} catch (error) {
  console.error('エラー:', error);
} finally {
  await browser.close();
}