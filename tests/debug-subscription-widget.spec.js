import { test, expect } from '@playwright/test';

test('サブスクリプションウィジェットのデバッグ', async ({ page }) => {
  // 本番環境にログイン
  await page.goto('https://reservation.meno-training.com/admin/login');
  await page.fill('input[type="email"]', 'naoki@yumeno-marketing.jp');
  await page.fill('input[type="password"]', 'Takahashi5000');
  await page.click('button[type="submit"]');

  // ダッシュボードに移動
  await page.waitForURL('**/admin', { timeout: 10000 });
  await page.waitForTimeout(5000); // もっと待つ

  console.log('📊 ダッシュボード読み込み完了');

  // ページのHTMLを確認
  const html = await page.content();
  const hasSubscriptionWidget = html.includes('SubscriptionStatsWidget') || html.includes('有効な契約数');
  console.log(`サブスクウィジェット含まれているか: ${hasSubscriptionWidget}`);

  // より広い範囲でウィジェットを探す
  const allStats = await page.locator('[class*="stat"]').all();
  console.log(`statを含む要素数: ${allStats.length}`);

  // サブスクリプションウィジェットを探す（複数の方法で）
  const widgets = await page.locator('.fi-wi-stats-overview-stat, [wire\\:id*="SubscriptionStatsWidget"]').all();

  console.log(`\n🔍 見つかったウィジェット数: ${widgets.length}`);

  // 各ウィジェットの内容を確認
  for (let i = 0; i < widgets.length; i++) {
    const widget = widgets[i];
    const heading = await widget.locator('.fi-wi-stats-overview-stat-label').textContent();
    const value = await widget.locator('.fi-wi-stats-overview-stat-value').textContent();
    const description = await widget.locator('.fi-wi-stats-overview-stat-description').textContent();

    console.log(`\nウィジェット ${i + 1}:`);
    console.log(`  タイトル: ${heading}`);
    console.log(`  値: ${value}`);
    console.log(`  説明: ${description}`);
  }

  // 店舗セレクトボックスを探す
  const storeSelect = await page.locator('select').first();

  if (storeSelect) {
    // 現在の選択値
    const currentStore = await storeSelect.inputValue();
    console.log(`\n🏪 現在の店舗: ${currentStore}`);

    // すべての店舗オプションを取得
    const options = await storeSelect.locator('option').all();
    console.log(`\n店舗オプション数: ${options.length}`);

    for (let i = 0; i < options.length; i++) {
      const option = options[i];
      const value = await option.getAttribute('value');
      const text = await option.textContent();
      console.log(`  ${i + 1}. value="${value}" text="${text}"`);
    }

    // 店舗を切り替えてみる
    if (options.length > 1) {
      const secondStoreValue = await options[1].getAttribute('value');
      console.log(`\n🔄 店舗を切り替えます: ${secondStoreValue}`);

      await storeSelect.selectOption(secondStoreValue);
      await page.waitForTimeout(3000); // Livewireの更新を待つ

      console.log('\n📊 切り替え後のウィジェット:');

      // 再度ウィジェットの値を確認
      const widgetsAfter = await page.locator('.fi-wi-stats-overview-stat').all();

      for (let i = 0; i < widgetsAfter.length; i++) {
        const widget = widgetsAfter[i];
        const heading = await widget.locator('.fi-wi-stats-overview-stat-label').textContent();
        const value = await widget.locator('.fi-wi-stats-overview-stat-value').textContent();

        console.log(`  ${heading}: ${value}`);
      }
    }
  } else {
    console.log('\n⚠️ 店舗セレクトボックスが見つかりません');
  }

  // データベースの実際の値を確認するため、ネットワークリクエストを監視
  console.log('\n🌐 Livewireリクエストを確認中...');

  page.on('response', async (response) => {
    if (response.url().includes('/livewire/update')) {
      const responseBody = await response.text().catch(() => '');
      if (responseBody.includes('SubscriptionStatsWidget')) {
        console.log('\n📡 Livewireレスポンス（一部）:');
        console.log(responseBody.substring(0, 500));
      }
    }
  });

  await page.waitForTimeout(2000);
});
