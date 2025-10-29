import { test, expect } from '@playwright/test';

test.describe('日次精算 - モーダル編集テスト', () => {
  test.beforeEach(async ({ page }) => {
    // 管理画面にログイン
    await page.goto('http://localhost:8000/admin/login', {
      waitUntil: 'networkidle',
      timeout: 30000
    });
    await page.fill('input#data\\.email', 'naoki@yumeno-marketing.jp');
    await page.fill('input#data\\.password', 'Takahashi5000');
    await page.click('button[type="submit"]');
    await page.waitForURL('**/admin**', { timeout: 30000 });

    // 日次精算ページへ移動
    await page.goto('http://localhost:8000/admin/sales/daily-closing', {
      waitUntil: 'networkidle',
      timeout: 30000
    });
  });

  test('計上済み予約を編集して一覧に反映されるか確認', async ({ page }) => {
    console.log('=== テスト開始 ===');

    // Wait for table to load
    await page.waitForSelector('table tbody tr', { timeout: 15000 });
    await page.waitForTimeout(2000);

    // Take screenshot to see what's on the page
    await page.screenshot({ path: 'tests/screenshots/daily-closing-initial-state.png', fullPage: true });
    console.log('📸 Initial state screenshot saved');

    // Count all rows
    const rowCount = await page.locator('table tbody tr').count();
    console.log(`📊 Total rows found: ${rowCount}`);

    // Count posted rows
    const postedCount = await page.locator('tr:has-text("計上済み")').count();
    console.log(`✅ Posted rows: ${postedCount}`);

    // 1. 計上済みの予約を探す
    const postedRow = page.locator('tr:has-text("計上済み")').first();
    await expect(postedRow).toBeVisible({ timeout: 10000 });

    // 計上済み予約の情報を取得
    const customerName = await postedRow.locator('td').nth(1).innerText();
    const beforePaymentMethod = await postedRow.locator('select').inputValue();
    const beforeAmount = await postedRow.locator('td').nth(5).innerText();

    console.log('📊 編集前のデータ:');
    console.log('  顧客:', customerName);
    console.log('  支払方法:', beforePaymentMethod);
    console.log('  金額:', beforeAmount);

    // 2. 編集ボタンをクリック
    await postedRow.locator('button:has-text("編集")').click();
    await page.waitForSelector('.fixed.inset-0', { state: 'visible' });
    await page.waitForTimeout(500);

    console.log('✏️ モーダルを開きました');

    // 3. オプションを追加（サブスク顧客でも課金される）
    const addOptionButton = page.locator('button:has-text("+ 追加")');
    const isOptionButtonVisible = await addOptionButton.isVisible().catch(() => false);

    if (isOptionButtonVisible) {
      await addOptionButton.click();
      await page.waitForTimeout(1000);
      console.log('➕ オプション追加ボタンをクリック');

      // オプション選択ドロップダウンから最初のオプションを選択
      const optionSelects = page.locator('select').filter({ hasText: 'オプションを選択' });
      const selectCount = await optionSelects.count();
      console.log(`📋 オプション選択欄の数: ${selectCount}`);

      if (selectCount > 0) {
        const lastSelect = optionSelects.last();
        const options = await lastSelect.locator('option').all();
        console.log(`📋 利用可能なオプション数: ${options.length}`);

        if (options.length > 1) {
          // 最初のオプション（index 1、0は"-- オプションを選択 --"）を選択
          const firstOption = await options[1].getAttribute('value');
          await lastSelect.selectOption(firstOption);
          await page.waitForTimeout(1000);

          const selectedText = await options[1].textContent();
          console.log(`✅ オプションを選択: ${selectedText}`);
        }
      }
    } else {
      console.log('⚠️ オプション追加ボタンが見つかりません（登録済みオプションがない可能性）');
    }

    // 4. 支払方法を変更
    const paymentMethodSelect = page.locator('select[wire\\:model="editorData.payment_method"]');
    await paymentMethodSelect.selectOption('ステラ');

    console.log('💳 支払方法をステラに変更');

    // 合計金額を確認
    const totalAmountInModal = await page.locator('text=/合計/').locator('..').locator('span.text-primary-600').innerText();
    console.log('📝 モーダル内の合計金額:', totalAmountInModal);

    // 5. 決定ボタンをクリック
    await page.locator('button:has-text("決定")').click();

    console.log('✅ 決定ボタンをクリック');

    // 6. 成功通知を待つ
    await expect(page.locator('text=/保存完了|売上更新/i')).toBeVisible({ timeout: 5000 });
    console.log('✅ 成功通知が表示されました');

    // モーダルが閉じるのを待つ
    await page.waitForSelector('.fixed.inset-0', { state: 'hidden', timeout: 5000 });
    await page.waitForTimeout(1000);

    console.log('📊 モーダルが閉じました。一覧画面を確認中...');

    // 7. 一覧画面で更新された値を確認
    const updatedRow = page.locator(`tr:has-text("${customerName}")`).first();
    await expect(updatedRow).toBeVisible();

    const afterPaymentMethod = await updatedRow.locator('select').inputValue();
    const afterAmount = await updatedRow.locator('td').nth(5).innerText();

    console.log('📊 編集後のデータ:');
    console.log('  顧客:', customerName);
    console.log('  支払方法:', afterPaymentMethod);
    console.log('  金額:', afterAmount);

    // 8. 検証
    console.log('\n=== 検証結果 ===');

    // 支払方法が変更されているか
    if (afterPaymentMethod === 'ステラ') {
      console.log('✅ 支払方法が正しく更新されています: ステラ');
    } else {
      console.log('❌ 支払方法が更新されていません:', afterPaymentMethod);
      console.log('   期待値: ステラ');
    }

    // 金額が変更されているか（オプション追加により金額が増えているはず）
    const beforeAmountNum = parseInt(beforeAmount.replace(/[^0-9]/g, '')) || 0;
    const afterAmountNum = parseInt(afterAmount.replace(/[^0-9]/g, '')) || 0;

    if (afterAmountNum > beforeAmountNum) {
      console.log(`✅ 金額が正しく更新されています: ${beforeAmount} → ${afterAmount}`);
    } else {
      console.log('❌ 金額が更新されていません:');
      console.log(`   編集前: ${beforeAmount} (${beforeAmountNum}円)`);
      console.log(`   編集後: ${afterAmount} (${afterAmountNum}円)`);
    }

    // 編集前と編集後で値が変わっているか
    if (beforePaymentMethod !== afterPaymentMethod || beforeAmount !== afterAmount) {
      console.log('✅ 一覧画面のデータが変更されています');
    } else {
      console.log('❌ 一覧画面のデータが変更されていません（古い値のまま）');
    }

    // スクリーンショット撮影
    await page.screenshot({ path: 'tests/screenshots/daily-closing-after-edit.png', fullPage: true });
    console.log('📸 スクリーンショットを保存しました: tests/screenshots/daily-closing-after-edit.png');

    // Assertionで検証
    expect(afterPaymentMethod).toBe('ステラ');
    expect(afterAmountNum).toBeGreaterThan(beforeAmountNum);
  });

  test('未計上→計上ボタンを押して数字が変わるか確認', async ({ page }) => {
    console.log('=== 未計上の計上テスト開始 ===');

    // 未計上の予約を探す
    const unpostedRow = page.locator('tr:not(:has-text("計上済み"))').filter({ has: page.locator('button:has-text("計上")') }).first();

    if (await unpostedRow.count() === 0) {
      console.log('⚠️ 未計上の予約がありません。テストをスキップします。');
      test.skip();
      return;
    }

    await expect(unpostedRow).toBeVisible();

    // 計上前の金額を取得
    const customerName = await unpostedRow.locator('td').nth(1).innerText();
    const beforeAmount = await unpostedRow.locator('input[type="number"], td').nth(5).innerText();

    console.log('📊 計上前:');
    console.log('  顧客:', customerName);
    console.log('  金額:', beforeAmount);

    // スクリーンショット（計上前）
    await page.screenshot({ path: 'tests/screenshots/before-posting.png', fullPage: true });

    // 計上ボタンをクリック
    await unpostedRow.locator('button:has-text("計上")').click();

    console.log('✅ 計上ボタンをクリック');

    // 成功通知を待つ
    await expect(page.locator('text=/計上完了/i')).toBeVisible({ timeout: 5000 });
    console.log('✅ 計上完了通知が表示されました');

    await page.waitForTimeout(1000);

    // 計上後の状態を確認
    const postedRow = page.locator(`tr:has-text("${customerName}")`).first();
    const afterAmount = await postedRow.locator('td').nth(5).innerText();
    const isPosted = await postedRow.locator('text=計上済み').count() > 0;

    console.log('📊 計上後:');
    console.log('  顧客:', customerName);
    console.log('  金額:', afterAmount);
    console.log('  計上済みフラグ:', isPosted ? 'あり' : 'なし');

    // スクリーンショット（計上後）
    await page.screenshot({ path: 'tests/screenshots/after-posting.png', fullPage: true });

    console.log('\n=== 検証結果 ===');
    if (isPosted) {
      console.log('✅ 計上済みフラグが表示されています');
    } else {
      console.log('❌ 計上済みフラグが表示されていません');
    }

    if (beforeAmount !== afterAmount) {
      console.log('⚠️ 計上前後で金額が変わりました:');
      console.log('   計上前:', beforeAmount);
      console.log('   計上後:', afterAmount);
    } else {
      console.log('✅ 計上前後で金額は同じです');
    }

    expect(isPosted).toBe(true);
  });
});
