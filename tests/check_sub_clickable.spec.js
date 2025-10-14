import { test, expect } from '@playwright/test';

test('サブ枠10:30がクリック可能か確認', async ({ page }) => {
  // ログイン
  await page.goto('http://localhost:8000/admin/login');
  await page.fill('input[name="email"]', 'admin@eye-training.com');
  await page.fill('input[name="password"]', 'password');
  await page.click('button[type="submit"]');
  await page.waitForURL('**/admin');
  await page.waitForLoadState('networkidle');

  console.log('✅ ログイン完了');

  // 少し待機
  await page.waitForTimeout(3000);

  // タイムラインテーブルを探す
  const timelineTable = page.locator('table.timeline-table');
  const isVisible = await timelineTable.isVisible().catch(() => false);

  if (!isVisible) {
    console.log('❌ タイムラインテーブルが見つかりません');
    return;
  }

  console.log('✅ タイムラインテーブル検出');

  // サブ行を探す
  const rows = await timelineTable.locator('tbody tr').all();
  console.log(`📊 行数: ${rows.length}`);

  let subRow = null;
  for (const row of rows) {
    const labelCell = row.locator('td').first();
    const labelText = await labelCell.textContent();

    if (labelText && labelText.includes('サブ')) {
      subRow = row;
      console.log('✅ サブ行を発見:', labelText.trim());
      break;
    }
  }

  if (!subRow) {
    console.log('❌ サブ行が見つかりません');
    return;
  }

  // サブ行のすべてのセルを取得
  const cells = await subRow.locator('td').all();
  console.log(`📊 サブ行のセル数: ${cells.length}`);

  // 各セルのクリック可否を確認
  let clickableCount = 0;
  let notClickableCount = 0;

  for (let i = 1; i < Math.min(cells.length, 20); i++) {  // 最初のセルはラベルなのでスキップ
    const cell = cells[i];

    // wire:click属性があるかチェック
    const hasWireClick = await cell.evaluate(el => el.hasAttribute('wire:click'));
    const cellClass = await cell.getAttribute('class') || '';
    const cellText = (await cell.textContent() || '').trim();

    // クリック可能なセルの条件
    const isClickable = hasWireClick &&
                        (cellClass.includes('clickable-slot') || cellClass.includes('empty-slot'));

    if (isClickable) {
      clickableCount++;
      console.log(`  セル${i}: ✅ クリック可能 (class: ${cellClass.substring(0, 30)}...)`);
    } else if (cellText === '' || cellText === 'BRK') {
      notClickableCount++;
      console.log(`  セル${i}: ❌ クリック不可 (class: ${cellClass.substring(0, 30)}..., text: ${cellText})`);
    }
  }

  console.log(`\n📊 結果: クリック可能=${clickableCount}, クリック不可=${notClickableCount}`);

  // 10:30付近のセルを詳しくチェック
  console.log('\n🔍 10:30付近のセルを詳細チェック:');
  for (let i = 5; i < Math.min(cells.length, 15); i++) {
    const cell = cells[i];
    const hasWireClick = await cell.evaluate(el => el.hasAttribute('wire:click'));
    const cellClass = await cell.getAttribute('class') || '';
    const cellStyle = await cell.getAttribute('style') || '';
    const onClick = await cell.getAttribute('onclick') || '';

    console.log(`  セル${i}:`);
    console.log(`    wire:click: ${hasWireClick}`);
    console.log(`    class: ${cellClass.substring(0, 50)}`);
    console.log(`    style: ${cellStyle.substring(0, 50)}`);
    console.log(`    onclick: ${onClick.substring(0, 50)}`);
  }

  // スクリーンショット撮影
  await page.screenshot({ path: 'sub-timeline-check.png', fullPage: true });
  console.log('\n📸 スクリーンショット保存: sub-timeline-check.png');
});
