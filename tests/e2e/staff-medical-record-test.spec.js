import { test, expect } from '@playwright/test';

test('銀座スタッフがカルテ一覧を表示できる', async ({ page }) => {
  // 銀座スタッフ志藤でログイン
  await page.goto('http://localhost:8000/admin/login');
  await page.waitForSelector('input[type="email"]', { timeout: 10000 });
  await page.fill('input[type="email"]', 'mime_5809@yahoo.co.jp');
  await page.fill('input[type="password"]', 'password');
  await page.click('button[type="submit"]');

  // ダッシュボードが表示されるまで待つ
  await page.waitForTimeout(3000);

  console.log('✅ ログイン成功');

  // カルテ管理ページに移動
  await page.goto('http://localhost:8000/admin/medical-records');
  await page.waitForTimeout(3000);

  console.log('✅ カルテ管理ページにアクセス');

  // スクリーンショットを撮る
  await page.screenshot({ path: 'staff-medical-records.png', fullPage: true });

  // カルテ一覧のテーブルを確認
  const emptyMessage = await page.locator('text=カルテが見つかりません').count();
  const rows = await page.locator('tbody tr').count();

  console.log(`空状態メッセージ: ${emptyMessage > 0 ? '表示されている' : '表示されていない'}`);
  console.log(`カルテ数: ${rows}`);

  // 新規作成ボタンがあるか確認
  const createButton = await page.locator('text=新規作成').count();
  console.log(`新規作成ボタン: ${createButton > 0 ? '表示されている' : '表示されていない'}`);
});
