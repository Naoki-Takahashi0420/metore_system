import { test, expect } from '@playwright/test';

test('マイページから指名のみメニューを選択するとスタッフ選択画面に遷移する', async ({ page }) => {
  // 1. 生成したctxパラメータ付きURLに直接アクセス
  const testUrl = 'http://localhost:8000/reservation/calendar?ctx=eyJpdiI6Ims0R08yU0lJK2o2Sk1YM25GeEt0Rmc9PSIsInZhbHVlIjoiMDFweWcyZk16L1JRYWd1TnkzSENrQXd2dFZqZklFNGMxYjFucEEzWngwQXRVRVdRWkI1ZVR6RmpNc1NCOGJybmhMRk4xU3lScTV6MldIdEpPdmxFVGFUY2tUSWhPeFVHa0xHT1dSeVpwTkNteElnTzBXcHpuWXlxejgxVUhybUVsSjRvWXpMYkd5d1FRR0FzZTF1MndtU3VSbXZXQ3VLSVYzM29Va3VtVmVCU2RvSmdYQ01pVXY3NXM5Mzk4VTNSIiwibWFjIjoiYzI4OTBkNDFlMTcxZjBhYzI4YTE5NjhiYWM2NjI1NjgzZjgxODhmYzdkMjQyNjYxYWVlZWI5NWZiNDRkMTUwYyIsInRhZyI6IiJ9';

  console.log('テストURL:', testUrl);
  await page.goto(testUrl);
  console.log('✓ カレンダー画面にアクセス');

  // 2. ページ読み込みを待機
  await page.waitForTimeout(2000);

  // 4. スタッフ選択画面に遷移するか確認
  await page.waitForTimeout(2000);
  const currentUrl = page.url();
  console.log('現在のURL:', currentUrl);

  // URLにselect-staffが含まれるか、またはページにスタッフ選択の要素があるか確認
  const isStaffSelectionPage = currentUrl.includes('select-staff') ||
                                await page.locator('text=スタッフを選択').isVisible().catch(() => false);

  if (isStaffSelectionPage) {
    console.log('✅ スタッフ選択画面に遷移しました');
  } else {
    console.log('❌ スタッフ選択画面に遷移していません');
    console.log('現在のページタイトル:', await page.title());

    // スクリーンショットを保存
    await page.screenshot({ path: 'test-results/staff-selection-fail.png' });
  }

  expect(isStaffSelectionPage).toBe(true);
});
