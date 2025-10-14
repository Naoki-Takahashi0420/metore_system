import { test, expect } from '@playwright/test';

test.describe('タイムラインサブ枠クリック可否テスト', () => {
  test('サブ枠の10:30がクリック可能かを確認', async ({ page }) => {
    // 管理画面にログイン
    await page.goto('http://localhost:8000/admin/login');
    await page.fill('input[name="email"]', 'admin@eye-training.com');
    await page.fill('input[name="password"]', 'password');
    await page.click('button[type="submit"]');
    await page.waitForURL('**/admin');

    // ダッシュボードに移動
    await page.goto('http://localhost:8000/admin');
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(2000);

    console.log('✅ ダッシュボードに到着');

    // タイムライン内の10:30のセルを探す
    // サブ枠の行を特定
    const subRow = page.locator('tr').filter({ hasText: 'サブ' }).first();
    console.log('サブ行を検出');

    // 10:30のセルを探す
    // タイムラインのヘッダーから10:30の列インデックスを特定
    const headers = await page.locator('thead th').allTextContents();
    console.log('ヘッダー:', headers);

    const timeIndex = headers.findIndex(h => h.includes('10:30'));
    console.log('10:30の列インデックス:', timeIndex);

    if (timeIndex === -1) {
      console.log('❌ 10:30の列が見つかりません');
      return;
    }

    // サブ行の10:30セルを取得
    const cells = await subRow.locator('td').all();
    console.log('サブ行のセル数:', cells.length);

    if (timeIndex >= cells.length) {
      console.log('❌ 10:30のセルインデックスが範囲外');
      return;
    }

    const targetCell = cells[timeIndex];

    // セルの状態を確認
    const cellClass = await targetCell.getAttribute('class');
    const cellText = await targetCell.textContent();
    const isDisabled = await targetCell.evaluate(el => {
      return el.hasAttribute('disabled') ||
             el.classList.contains('disabled') ||
             el.style.pointerEvents === 'none';
    });

    console.log('📊 セル情報:');
    console.log('  クラス:', cellClass);
    console.log('  テキスト:', cellText);
    console.log('  無効化されている:', isDisabled);

    // クリック可能かテスト
    try {
      const boundingBox = await targetCell.boundingBox();
      console.log('  位置情報:', boundingBox);

      if (!boundingBox) {
        console.log('❌ セルが表示されていません');
        return;
      }

      // クリックを試みる
      await targetCell.click({ timeout: 5000 });
      console.log('✅ セルがクリックできました');

      // モーダルが開いたか確認
      await page.waitForTimeout(1000);
      const modalVisible = await page.locator('[role="dialog"], .modal').isVisible().catch(() => false);

      if (modalVisible) {
        console.log('✅ 予約モーダルが開きました');
      } else {
        console.log('⚠️ セルはクリックできたがモーダルが開きませんでした');
      }

    } catch (error) {
      console.log('❌ セルがクリックできません:', error.message);

      // セルにワイヤーIDがあるか確認（Livewireの状態）
      const wireId = await targetCell.getAttribute('wire:click');
      const wireKey = await targetCell.getAttribute('wire:key');
      console.log('  wire:click:', wireId);
      console.log('  wire:key:', wireKey);
    }

    // スクリーンショットを撮る
    await page.screenshot({
      path: 'timeline-sub-10-30-state.png',
      fullPage: true
    });
    console.log('📸 スクリーンショット保存: timeline-sub-10-30-state.png');
  });

  test('サブ枠の空きスロットがすべてクリック可能か確認', async ({ page }) => {
    await page.goto('http://localhost:8000/admin/login');
    await page.fill('input[name="email"]', 'admin@eye-training.com');
    await page.fill('input[name="password"]', 'password');
    await page.click('button[type="submit"]');
    await page.waitForURL('**/admin');

    await page.goto('http://localhost:8000/admin');
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(2000);

    // サブ行のすべてのセルを取得
    const subRow = page.locator('tr').filter({ hasText: 'サブ' }).first();
    const cells = await subRow.locator('td').all();

    console.log('\n📋 サブ枠全セルのクリック可否チェック:');

    for (let i = 0; i < Math.min(cells.length, 20); i++) {
      const cell = cells[i];
      const cellText = await cell.textContent();
      const cellClass = await cell.getAttribute('class');

      // 予約がないセル（空きセル）のみチェック
      const hasReservation = cellText && cellText.trim().length > 0 && !cellText.includes('NEW');

      if (!hasReservation) {
        const isClickable = await cell.evaluate(el => {
          const style = window.getComputedStyle(el);
          return style.pointerEvents !== 'none' &&
                 !el.hasAttribute('disabled') &&
                 !el.classList.contains('disabled');
        });

        console.log(`  セル${i}: クリック${isClickable ? '可能✅' : '不可❌'} (class: ${cellClass?.substring(0, 30)}...)`);
      }
    }
  });
});
