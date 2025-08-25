import { test, expect } from '@playwright/test';

test.describe('本番環境 総合テスト', () => {
  const baseURL = 'http://13.115.38.179';
  
  test('トップページが正常に表示される', async ({ page }) => {
    await page.goto(baseURL);
    
    // トップページから/storesにリダイレクトされることを確認
    await expect(page).toHaveURL(`${baseURL}/stores`);
    
    // ページが正常に読み込まれることを確認
    await expect(page.locator('body')).toBeVisible();
  });

  test('管理画面ログインページが表示される', async ({ page }) => {
    await page.goto(`${baseURL}/admin/login`);
    
    // ログインフォームが表示されることを確認
    await expect(page.locator('input[type="email"]')).toBeVisible();
    await expect(page.locator('input[type="password"]')).toBeVisible();
    await expect(page.locator('button[type="submit"]')).toBeVisible();
    
    // タイトルを確認
    await expect(page).toHaveTitle(/管理画面|Admin/);
  });

  test('管理者ログインが成功する', async ({ page }) => {
    await page.goto(`${baseURL}/admin/login`);
    
    // ログイン情報を入力
    await page.fill('input[type="email"]', 'admin@xsyumeno.com');
    await page.fill('input[type="password"]', 'password');
    
    // ログインボタンをクリック
    await page.click('button[type="submit"]');
    
    // ダッシュボードにリダイレクトされることを確認（最大30秒待機）
    await page.waitForURL(`${baseURL}/admin`, { 
      timeout: 30000,
      waitUntil: 'networkidle' 
    });
    
    // ダッシュボードが表示されることを確認
    await expect(page.locator('body')).toContainText(/ダッシュボード|Dashboard/);
  });

  test('ログイン後の管理画面メニューが表示される', async ({ page }) => {
    // ログイン処理
    await page.goto(`${baseURL}/admin/login`);
    await page.fill('input[type="email"]', 'admin@xsyumeno.com');
    await page.fill('input[type="password"]', 'password');
    await page.click('button[type="submit"]');
    
    // ダッシュボードに遷移を待つ
    await page.waitForURL(`${baseURL}/admin`, { timeout: 30000 });
    
    // メニュー項目が表示されることを確認
    const menuItems = [
      '顧客',
      'メニュー',
      '予約',
      '店舗',
      'ユーザー'
    ];
    
    for (const item of menuItems) {
      const menuItem = page.locator(`text=${item}`).first();
      await expect(menuItem).toBeVisible({ timeout: 10000 });
    }
  });

  test('ログアウトが正常に動作する', async ({ page }) => {
    // ログイン処理
    await page.goto(`${baseURL}/admin/login`);
    await page.fill('input[type="email"]', 'admin@xsyumeno.com');
    await page.fill('input[type="password"]', 'password');
    await page.click('button[type="submit"]');
    
    // ダッシュボードに遷移を待つ
    await page.waitForURL(`${baseURL}/admin`, { timeout: 30000 });
    
    // ユーザーメニューをクリック（アバターアイコンなど）
    const userMenu = page.locator('[aria-label*="user"]').first();
    if (await userMenu.isVisible()) {
      await userMenu.click();
    }
    
    // ログアウトボタンをクリック
    const logoutButton = page.locator('text=ログアウト').or(page.locator('text=Logout')).first();
    await logoutButton.click();
    
    // ログインページにリダイレクトされることを確認
    await page.waitForURL(`${baseURL}/admin/login`, { timeout: 30000 });
  });

  test('モバイルレスポンシブ表示の確認', async ({ page }) => {
    // モバイルサイズに設定
    await page.setViewportSize({ width: 375, height: 667 });
    
    await page.goto(`${baseURL}/stores`);
    
    // ページが正常に表示されることを確認
    await expect(page.locator('body')).toBeVisible();
    
    // モバイルメニューが存在する場合の確認
    const mobileMenu = page.locator('[aria-label*="menu"]').first();
    if (await mobileMenu.isVisible()) {
      await mobileMenu.click();
      // メニューが開くことを確認
      await page.waitForTimeout(500);
    }
  });

  test('404ページの確認', async ({ page }) => {
    await page.goto(`${baseURL}/non-existent-page`, { waitUntil: 'networkidle' });
    
    // 404エラーまたはリダイレクトを確認
    const pageContent = await page.content();
    const is404 = pageContent.includes('404') || pageContent.includes('Not Found');
    const isRedirected = page.url() === `${baseURL}/` || page.url() === `${baseURL}/stores`;
    
    expect(is404 || isRedirected).toBeTruthy();
  });
});

test.describe('ローカル環境 総合テスト', () => {
  const baseURL = 'http://127.0.0.1:8000';
  
  test('ローカル環境のトップページが表示される', async ({ page }) => {
    await page.goto(baseURL);
    
    // ページが正常に読み込まれることを確認
    await expect(page.locator('body')).toBeVisible();
  });

  test('ローカル環境の管理者ログインが成功する', async ({ page }) => {
    await page.goto(`${baseURL}/admin/login`);
    
    // ログイン情報を入力
    await page.fill('input[type="email"]', 'superadmin@eye-training.com');
    await page.fill('input[type="password"]', 'password');
    
    // ログインボタンをクリック
    await page.click('button[type="submit"]');
    
    // ダッシュボードにリダイレクトされることを確認
    await page.waitForURL(`${baseURL}/admin`, { 
      timeout: 30000,
      waitUntil: 'networkidle' 
    });
    
    // ダッシュボードが表示されることを確認
    await expect(page.locator('body')).toContainText(/ダッシュボード|Dashboard/);
  });
});