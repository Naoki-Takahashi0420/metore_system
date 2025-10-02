import { test, expect } from '@playwright/test';

test('日程変更画面で予約済み時間帯が正しく表示される', async ({ page }) => {
  // 管理画面にログイン
  await page.goto('http://localhost:8000/admin/login');
  await page.fill('input[name="email"]', 'admin@eye-training.com');
  await page.fill('input[name="password"]', 'password');
  await page.click('button[type="submit"]');

  // ログイン完了を待つ
  await page.waitForURL('**/admin**');

  // 日程変更画面に直接アクセス
  await page.goto('http://localhost:8000/admin/reservations/74/reschedule');

  // ページが読み込まれるまで待つ
  await page.waitForLoadState('networkidle');

  // スクリーンショットを撮る
  await page.screenshot({ path: '/tmp/reschedule-page.png', fullPage: true });

  console.log('スクリーンショットを保存: /tmp/reschedule-page.png');

  // 10:00の予約状況を確認
  const slot1000 = await page.locator('button:has-text("現")').first();
  const exists = await slot1000.count();

  if (exists > 0) {
    console.log('✅ 10:00に「現」マークが表示されている（現在の予約）');
  } else {
    console.log('❌ 10:00に「現」マークが見つからない');
  }

  // 13:00の予約状況を確認（予約済みのはず）
  const allSlots = await page.locator('table td button').allTextContents();
  console.log('表示されているスロット:', allSlots.slice(0, 20));

  // ○が多すぎないかチェック
  const availableSlots = await page.locator('button:has-text("○")').count();
  const unavailableSlots = await page.locator('button:has-text("×")').count();
  const currentSlot = await page.locator('button:has-text("現")').count();

  console.log('予約可能（○）:', availableSlots);
  console.log('予約不可（×）:', unavailableSlots);
  console.log('現在の予約（現）:', currentSlot);

  // 予約が6件あるので、少なくとも5件は×または現になるはず
  expect(unavailableSlots + currentSlot).toBeGreaterThanOrEqual(5);
});
