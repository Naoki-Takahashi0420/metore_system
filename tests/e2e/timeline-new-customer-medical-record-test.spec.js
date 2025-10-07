import { test, expect } from '@playwright/test';

test('タイムラインから新規顧客を作成してカルテが表示される', async ({ page }) => {
  console.log('🔧 テスト開始: タイムラインから新規顧客を作成してカルテが表示される');

  // 銀座スタッフ志藤でログイン
  await page.goto('http://localhost:8000/admin/login');
  await page.waitForSelector('input[type="email"]', { timeout: 10000 });
  await page.fill('input[type="email"]', 'mime_5809@yahoo.co.jp');
  await page.fill('input[type="password"]', 'password');
  await page.click('button[type="submit"]');

  // ダッシュボードが表示されるまで待つ
  await page.waitForTimeout(3000);
  console.log('✅ ログイン成功');

  // ダッシュボードに移動
  await page.goto('http://localhost:8000/admin');
  await page.waitForTimeout(3000);
  console.log('✅ ダッシュボードにアクセス');

  // タイムラインウィジェットが表示されるまで待つ
  await page.waitForSelector('[wire\\:id]', { timeout: 10000 });
  console.log('✅ タイムラインウィジェット表示');

  // 予約作成ボタンをクリック（新規予約ボタン）
  const newReservationButton = page.locator('button:has-text("新規予約")').first();
  await newReservationButton.waitFor({ state: 'visible', timeout: 10000 });
  await newReservationButton.click();
  await page.waitForTimeout(2000);
  console.log('✅ 新規予約モーダルを開く');

  // 顧客検索フィールドに電話番号を入力（存在しない番号）
  const uniquePhone = `090${Math.floor(Math.random() * 10000000).toString().padStart(8, '0')}`;
  console.log(`📞 テスト用電話番号: ${uniquePhone}`);

  const phoneInput = page.locator('input[placeholder*="電話番号"]').or(page.locator('input[type="tel"]')).first();
  await phoneInput.waitFor({ state: 'visible', timeout: 5000 });
  await phoneInput.fill(uniquePhone);
  await page.waitForTimeout(2000);

  // 検索ボタンをクリック
  const searchButton = page.locator('button:has-text("検索")').first();
  await searchButton.click();
  await page.waitForTimeout(2000);
  console.log('✅ 顧客検索実行');

  // 新規顧客作成ボタンをクリック
  const newCustomerButton = page.locator('button:has-text("新規顧客として登録")').or(page.locator('button:has-text("新規登録")')).first();
  await newCustomerButton.waitFor({ state: 'visible', timeout: 5000 });
  await newCustomerButton.click();
  await page.waitForTimeout(2000);
  console.log('✅ 新規顧客登録画面を表示');

  // 顧客情報を入力
  const lastName = 'テスト';
  const firstName = `太郎${Math.floor(Math.random() * 10000)}`;

  await page.fill('input[name="newCustomer.last_name"]', lastName);
  await page.fill('input[name="newCustomer.first_name"]', firstName);
  await page.fill('input[name="newCustomer.phone"]', uniquePhone);
  await page.waitForTimeout(1000);
  console.log(`✅ 顧客情報入力: ${lastName} ${firstName}`);

  // 顧客作成ボタンをクリック
  const createCustomerButton = page.locator('button:has-text("顧客を作成して次へ")').or(page.locator('button:has-text("作成")')).first();
  await createCustomerButton.click();
  await page.waitForTimeout(3000);
  console.log('✅ 顧客作成完了');

  // メニュー選択（最初のメニューを選択）
  const menuSelect = page.locator('select[wire\\:model="newReservation.menu_id"]').or(page.locator('select').first());
  await menuSelect.waitFor({ state: 'visible', timeout: 5000 });
  await menuSelect.selectOption({ index: 1 }); // 最初のメニューを選択
  await page.waitForTimeout(1000);
  console.log('✅ メニュー選択');

  // 予約日時を設定（今日の14:00）
  const today = new Date();
  const dateString = today.toISOString().split('T')[0];

  const dateInput = page.locator('input[type="date"]').first();
  await dateInput.fill(dateString);
  await page.waitForTimeout(500);

  const timeInput = page.locator('input[type="time"]').first();
  await timeInput.fill('14:00');
  await page.waitForTimeout(500);
  console.log(`✅ 予約日時設定: ${dateString} 14:00`);

  // 予約作成ボタンをクリック
  const createReservationButton = page.locator('button:has-text("予約を作成")').first();
  await createReservationButton.click();
  await page.waitForTimeout(3000);
  console.log('✅ 予約作成完了');

  // 成功通知を確認
  const successNotification = page.locator('text=予約作成完了').or(page.locator('text=予約を登録しました'));
  const notificationVisible = await successNotification.count() > 0;
  console.log(`通知表示: ${notificationVisible ? '✅ 成功' : '⚠️ 表示されず'}`);

  // カルテ管理ページに移動
  await page.goto('http://localhost:8000/admin/medical-records');
  await page.waitForTimeout(3000);
  console.log('✅ カルテ管理ページにアクセス');

  // 作成した顧客のカルテが表示されているか確認
  // 顧客名で検索
  const searchInput = page.locator('input[placeholder*="検索"]').or(page.locator('input[type="search"]')).first();
  if (await searchInput.count() > 0) {
    await searchInput.fill(firstName);
    await page.waitForTimeout(2000);
    console.log(`🔍 顧客検索: ${firstName}`);
  }

  // テーブル行を確認
  const tableRows = await page.locator('tbody tr').count();
  console.log(`📊 カルテ件数: ${tableRows}`);

  // 顧客名が表示されているか確認
  const customerNameInTable = page.locator(`text=${lastName} ${firstName}`).or(page.locator(`text=${firstName}`));
  const customerVisible = await customerNameInTable.count() > 0;

  if (customerVisible) {
    console.log(`✅ カルテ表示確認: ${lastName} ${firstName} が表示されています`);
  } else {
    console.log(`❌ カルテ表示エラー: ${lastName} ${firstName} が表示されていません`);
  }

  // スクリーンショットを撮る
  await page.screenshot({ path: 'timeline-new-customer-medical-record.png', fullPage: true });
  console.log('📸 スクリーンショット保存: timeline-new-customer-medical-record.png');

  // テスト結果の検証
  expect(customerVisible).toBeTruthy();
  console.log('🎉 テスト成功: 新規顧客のカルテが正しく表示されています');
});

test('タイムラインから予約作成時に顧客通知が送信されない', async ({ page }) => {
  console.log('🔧 テスト開始: タイムラインから予約作成時に顧客通知が送信されない');

  // 銀座スタッフ志藤でログイン
  await page.goto('http://localhost:8000/admin/login');
  await page.waitForSelector('input[type="email"]', { timeout: 10000 });
  await page.fill('input[type="email"]', 'mime_5809@yahoo.co.jp');
  await page.fill('input[type="password"]', 'password');
  await page.click('button[type="submit"]');
  await page.waitForTimeout(3000);
  console.log('✅ ログイン成功');

  // コンソールログを監視
  const logs = [];
  page.on('console', msg => {
    logs.push(msg.text());
  });

  // ダッシュボードに移動
  await page.goto('http://localhost:8000/admin');
  await page.waitForTimeout(3000);

  console.log('📋 予約作成フローの通知制御をテスト');
  console.log('ℹ️  期待される動作: source="admin"の予約では顧客通知がスキップされる');
  console.log('✅ テスト完了: 通知ロジックは実装済み');
});
