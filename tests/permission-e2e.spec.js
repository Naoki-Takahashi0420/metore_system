import { test, expect } from '@playwright/test';

const BASE_URL = 'http://127.0.0.1:8000';

test.describe('権限システムE2Eテスト', () => {
  
  test('スーパーアドミン: 全データアクセス可能', async ({ page }) => {
    // ログイン
    await page.goto(`${BASE_URL}/admin/login`);
    await page.fill('input[name="email"]', 'superadmin@eye-training.com');
    await page.fill('input[name="password"]', 'password');
    await page.click('button[type="submit"]');
    
    // ダッシュボード確認
    await page.waitForURL(`${BASE_URL}/admin`);
    
    // ユーザー管理が表示される
    await expect(page.getByText('ユーザー管理')).toBeVisible();
    
    // ユーザー一覧ページ
    await page.click('text=ユーザー管理');
    await page.waitForURL(`${BASE_URL}/admin/users`);
    
    // 全店舗のユーザーが表示される
    await expect(page.getByText('東京店長')).toBeVisible();
    await expect(page.getByText('大阪スタッフ')).toBeVisible();
    
    // 新規作成ボタンが表示される
    await expect(page.getByText('新規作成')).toBeVisible();
  });

  test('Manager権限: 所属店舗のみ表示', async ({ page }) => {
    // ログイン
    await page.goto(`${BASE_URL}/admin/login`);
    await page.fill('input[name="email"]', 'manager.tokyo@test.com');
    await page.fill('input[name="password"]', 'password');
    await page.click('button[type="submit"]');
    
    // ダッシュボード確認
    await page.waitForURL(`${BASE_URL}/admin`);
    
    // ユーザー管理が表示される
    await expect(page.getByText('ユーザー管理')).toBeVisible();
    
    // ユーザー一覧ページ
    await page.click('text=ユーザー管理');
    await page.waitForURL(`${BASE_URL}/admin/users`);
    
    // 東京店のユーザーのみ表示される
    await expect(page.getByText('東京店長')).toBeVisible();
    
    // 大阪店のユーザーは表示されない
    const osakaStaff = await page.locator('text=大阪スタッフ').count();
    expect(osakaStaff).toBe(0);
    
    // 新規作成ボタンが表示される
    await expect(page.getByText('新規作成')).toBeVisible();
  });

  test('Staff権限: ユーザー管理非表示', async ({ page }) => {
    // ログイン
    await page.goto(`${BASE_URL}/admin/login`);
    await page.fill('input[name="email"]', 'staff.osaka@test.com');
    await page.fill('input[name="password"]', 'password');
    await page.click('button[type="submit"]');
    
    // ダッシュボード確認
    await page.waitForURL(`${BASE_URL}/admin`);
    
    // ユーザー管理が表示されない
    const userManagement = await page.locator('text=ユーザー管理').count();
    expect(userManagement).toBe(0);
    
    // 直接URLアクセスも拒否される
    await page.goto(`${BASE_URL}/admin/users`);
    await expect(page.locator('text=403')).toBeVisible();
  });

  test('Owner権限: 複数店舗管理', async ({ page }) => {
    // ログイン
    await page.goto(`${BASE_URL}/admin/login`);
    await page.fill('input[name="email"]', 'owner.multi@test.com');
    await page.fill('input[name="password"]', 'password');
    await page.click('button[type="submit"]');
    
    // ダッシュボード確認
    await page.waitForURL(`${BASE_URL}/admin`);
    
    // ユーザー管理が表示される
    await expect(page.getByText('ユーザー管理')).toBeVisible();
    
    // ユーザー一覧ページ
    await page.click('text=ユーザー管理');
    await page.waitForURL(`${BASE_URL}/admin/users`);
    
    // 東京と大阪の両方のユーザーが表示される
    await expect(page.getByText('東京店長')).toBeVisible();
    await expect(page.getByText('大阪スタッフ')).toBeVisible();
    
    // 名古屋のユーザーは表示されない（管理対象外）
    const nagoyaUser = await page.locator('text=名古屋').count();
    expect(nagoyaUser).toBe(0);
  });

  test('権限による編集制限', async ({ page }) => {
    // Managerでログイン
    await page.goto(`${BASE_URL}/admin/login`);
    await page.fill('input[name="email"]', 'manager.tokyo@test.com');
    await page.fill('input[name="password"]', 'password');
    await page.click('button[type="submit"]');
    
    // ユーザー一覧
    await page.click('text=ユーザー管理');
    await page.waitForURL(`${BASE_URL}/admin/users`);
    
    // 東京店のユーザーを編集
    await page.click('text=東京店長');
    await page.waitForURL(/.*\/edit$/);
    
    // 編集フォームが表示される
    await expect(page.locator('input[name="name"]')).toBeVisible();
    
    // 保存ボタンが表示される
    await expect(page.getByText('保存')).toBeVisible();
  });

  test('ロール選択と管理可能店舗', async ({ page }) => {
    // スーパーアドミンでログイン
    await page.goto(`${BASE_URL}/admin/login`);
    await page.fill('input[name="email"]', 'superadmin@eye-training.com');
    await page.fill('input[name="password"]', 'password');
    await page.click('button[type="submit"]');
    
    // 新規ユーザー作成
    await page.click('text=ユーザー管理');
    await page.click('text=新規作成');
    await page.waitForURL(`${BASE_URL}/admin/users/create`);
    
    // ロール選択フィールドが表示される
    await expect(page.locator('text=ロール')).toBeVisible();
    
    // デフォルトでは管理可能店舗は非表示
    const manageableStores = await page.locator('text=管理可能店舗').count();
    expect(manageableStores).toBe(0);
    
    // オーナーロールを選択
    await page.click('text=ロール');
    await page.click('text=オーナー');
    
    // 管理可能店舗が表示される
    await expect(page.locator('text=管理可能店舗')).toBeVisible();
  });

  test('異なる権限での予約管理', async ({ page }) => {
    // Staffでログイン
    await page.goto(`${BASE_URL}/admin/login`);
    await page.fill('input[name="email"]', 'staff.osaka@test.com');
    await page.fill('input[name="password"]', 'password');
    await page.click('button[type="submit"]');
    
    // 予約管理が表示される
    await expect(page.getByText('予約管理')).toBeVisible();
    
    // 予約一覧ページ
    await page.click('text=予約管理');
    await page.waitForURL(`${BASE_URL}/admin/reservations`);
    
    // 大阪店の予約のみ表示される（店舗フィルタリング確認）
    const storeColumn = await page.locator('text=大阪支店').count();
    expect(storeColumn).toBeGreaterThan(0);
  });
});