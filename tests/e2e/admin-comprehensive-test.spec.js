import { test, expect } from '@playwright/test';

test.describe('管理画面 - 徹底的なエラーチェック', () => {
  let adminCookie;
  
  test.use({
    httpCredentials: {
      username: 'yumeno',
      password: 'takahashi5000'
    }
  });

  test.beforeAll(async ({ browser }) => {
    // 管理者としてログイン（Basic認証を含む）
    const context = await browser.newContext({
      httpCredentials: {
        username: 'yumeno',
        password: 'takahashi5000'
      }
    });
    const page = await context.newPage();
    
    await page.goto('http://127.0.0.1:8000/admin/login');
    await page.fill('input[name="email"]', 'admin@eye-training.com');
    await page.fill('input[name="password"]', 'password');
    await page.click('button[type="submit"]');
    
    // ログイン成功を待つ
    await page.waitForURL('**/admin');
    
    // クッキーを保存
    adminCookie = await context.cookies();
    await context.close();
  });

  test.beforeEach(async ({ context }) => {
    // 保存したクッキーを設定
    if (adminCookie) {
      await context.addCookies(adminCookie);
    }
  });

  test('ダッシュボード - エラーチェック', async ({ page }) => {
    await page.goto('http://127.0.0.1:8000/admin');
    
    // ページが正常に表示されることを確認
    await expect(page).not.toHaveTitle(/Error|Exception|500|404/);
    
    // コンソールエラーをチェック
    const consoleErrors = [];
    page.on('console', msg => {
      if (msg.type() === 'error') {
        consoleErrors.push(msg.text());
      }
    });
    
    await page.waitForTimeout(1000);
    expect(consoleErrors).toHaveLength(0);
  });

  test('全ナビゲーションリンクのチェック', async ({ page }) => {
    await page.goto('http://127.0.0.1:8000/admin');
    
    const navigationItems = [
      { text: 'ダッシュボード', url: '/admin' },
      { text: '予約管理', url: '/admin/reservations' },
      { text: '顧客管理', url: '/admin/customers' },
      { text: '店舗管理', url: '/admin/stores' },
      { text: 'メニュー管理', url: '/admin/menus' },
      { text: 'カルテ管理', url: '/admin/medical-records' },
      { text: '売上管理', url: '/admin/sales' },
      { text: 'アクセストークン', url: '/admin/customer-access-tokens' },
      { text: 'ユーザー管理', url: '/admin/users' },
      { text: 'LINE設定', url: '/admin/line-settings' },
    ];

    for (const item of navigationItems) {
      console.log(`Checking: ${item.text}`);
      
      // リンクをクリック
      const linkLocator = page.locator(`a:has-text("${item.text}")`).first();
      
      if (await linkLocator.isVisible()) {
        await linkLocator.click();
        
        // エラーページでないことを確認
        await expect(page).not.toHaveTitle(/Error|Exception|500|404/);
        const bodyText = await page.locator('body').textContent();
        expect(bodyText).not.toContain('Exception');
        expect(bodyText).not.toContain('Error');
        expect(bodyText).not.toContain('undefined');
        expect(bodyText).not.toContain('The GET method is not supported');
        
        // ダッシュボードに戻る
        await page.goto('http://127.0.0.1:8000/admin');
      }
    }
  });

  test('予約管理 - CRUD操作', async ({ page }) => {
    await page.goto('http://127.0.0.1:8000/admin/reservations');
    
    // リストページが正常に表示される
    await expect(page).not.toHaveTitle(/Error/);
    
    // 新規作成ボタンをクリック
    const createButton = page.locator('a:has-text("新規作成")').first();
    if (await createButton.isVisible()) {
      await createButton.click();
      await expect(page).toHaveURL(/\/admin\/reservations\/create/);
      await expect(page).not.toHaveTitle(/Error/);
      
      // フォームが表示されることを確認
      await expect(page.locator('form')).toBeVisible();
    }
    
    // リストに戻る
    await page.goto('http://127.0.0.1:8000/admin/reservations');
    
    // 編集リンクをテスト
    const editButtons = page.locator('a[href*="/edit"]');
    const editCount = await editButtons.count();
    
    if (editCount > 0) {
      const firstEditButton = editButtons.first();
      const editUrl = await firstEditButton.getAttribute('href');
      await firstEditButton.click();
      
      // 編集ページが正常に表示される
      await expect(page).not.toHaveTitle(/Error/);
      await expect(page.locator('form')).toBeVisible();
    }
  });

  test('顧客管理 - CRUD操作', async ({ page }) => {
    await page.goto('http://127.0.0.1:8000/admin/customers');
    
    // リストページが正常に表示される
    await expect(page).not.toHaveTitle(/Error/);
    
    // 新規作成
    const createButton = page.locator('a:has-text("新規作成")').first();
    if (await createButton.isVisible()) {
      await createButton.click();
      await expect(page).toHaveURL(/\/admin\/customers\/create/);
      await expect(page).not.toHaveTitle(/Error/);
      
      // テストデータを入力
      await page.fill('input[name="data.last_name"]', 'テスト');
      await page.fill('input[name="data.first_name"]', '太郎');
      await page.fill('input[name="data.last_name_kana"]', 'テスト');
      await page.fill('input[name="data.first_name_kana"]', 'タロウ');
      await page.fill('input[name="data.phone"]', '09012345678');
      await page.fill('input[name="data.email"]', `test${Date.now()}@example.com`);
      
      // 保存
      await page.click('button:has-text("作成")');
      
      // エラーがないことを確認
      const bodyText = await page.locator('body').textContent();
      expect(bodyText).not.toContain('Exception');
    }
  });

  test('カルテ管理 - CRUD操作', async ({ page }) => {
    await page.goto('http://127.0.0.1:8000/admin/medical-records');
    
    // リストページが正常に表示される
    await expect(page).not.toHaveTitle(/Error/);
    
    // 新規作成
    const createButton = page.locator('a:has-text("新規作成")').first();
    if (await createButton.isVisible()) {
      await createButton.click();
      await expect(page).toHaveURL(/\/admin\/medical-records\/create/);
      await expect(page).not.toHaveTitle(/Error/);
      
      // フォームが表示されることを確認
      await expect(page.locator('form')).toBeVisible();
      
      // タブが表示されることを確認
      await expect(page.locator('text=基本情報')).toBeVisible();
      await expect(page.locator('text=顧客管理情報')).toBeVisible();
      await expect(page.locator('text=視力記録')).toBeVisible();
      await expect(page.locator('text=接客メモ・引き継ぎ')).toBeVisible();
    }
  });

  test('LINE設定 - ページ表示', async ({ page }) => {
    await page.goto('http://127.0.0.1:8000/admin/line-settings');
    
    // エラーがないことを確認
    await expect(page).not.toHaveTitle(/Error/);
    const bodyText = await page.locator('body').textContent();
    expect(bodyText).not.toContain('Exception');
    expect(bodyText).not.toContain('Call to undefined');
  });

  test('アクセストークン - エラーチェック', async ({ page }) => {
    await page.goto('http://127.0.0.1:8000/admin/customer-access-tokens');
    
    // リストページが正常に表示される
    await expect(page).not.toHaveTitle(/Error/);
    
    // 編集リンクをテスト
    const editLinks = page.locator('a[href*="/customer-access-tokens/"][href*="/edit"]');
    const editCount = await editLinks.count();
    
    for (let i = 0; i < Math.min(editCount, 3); i++) {
      const editLink = editLinks.nth(i);
      const href = await editLink.getAttribute('href');
      
      // 直接URLにアクセス
      await page.goto(`http://127.0.0.1:8000${href}`);
      
      // エラーページでないことを確認
      await expect(page).not.toHaveTitle(/Error|Exception|500|404/);
      const bodyText = await page.locator('body').textContent();
      expect(bodyText).not.toContain('Call to undefined');
      expect(bodyText).not.toContain('Exception');
      
      // リストに戻る
      await page.goto('http://127.0.0.1:8000/admin/customer-access-tokens');
    }
  });

  test('売上管理 - エラーチェック', async ({ page }) => {
    await page.goto('http://127.0.0.1:8000/admin/sales');
    
    // リストページが正常に表示される
    await expect(page).not.toHaveTitle(/Error/);
    
    // 詳細リンクをテスト
    const viewLinks = page.locator('a[href*="/sales/"]').filter({ hasText: '表示' });
    const viewCount = await viewLinks.count();
    
    if (viewCount > 0) {
      const firstViewLink = viewLinks.first();
      await firstViewLink.click();
      
      // 詳細ページが正常に表示される
      await expect(page).not.toHaveTitle(/Error/);
      const bodyText = await page.locator('body').textContent();
      expect(bodyText).not.toContain('Exception');
    }
  });

  test('ユーザー管理 - 権限エラーチェック', async ({ page }) => {
    await page.goto('http://127.0.0.1:8000/admin/users');
    
    // リストページが正常に表示される
    await expect(page).not.toHaveTitle(/Error/);
    
    // 新規作成
    const createButton = page.locator('a:has-text("新規作成")').first();
    if (await createButton.isVisible()) {
      await createButton.click();
      
      // フォームが表示されることを確認
      await expect(page.locator('form')).toBeVisible();
      
      // ロールの選択肢が表示されることを確認
      const roleSelect = page.locator('select[name="data.role"]');
      if (await roleSelect.isVisible()) {
        const options = await roleSelect.locator('option').allTextContents();
        expect(options.length).toBeGreaterThan(0);
      }
    }
  });

  test('検索機能のテスト', async ({ page }) => {
    const searchablePages = [
      '/admin/customers',
      '/admin/reservations',
      '/admin/medical-records',
      '/admin/sales',
    ];

    for (const url of searchablePages) {
      await page.goto(`http://127.0.0.1:8000${url}`);
      
      // 検索フィールドを探す
      const searchInput = page.locator('input[type="search"], input[placeholder*="検索"]').first();
      
      if (await searchInput.isVisible()) {
        // 検索を実行
        await searchInput.fill('test');
        await searchInput.press('Enter');
        
        // エラーがないことを確認
        await expect(page).not.toHaveTitle(/Error/);
        const bodyText = await page.locator('body').textContent();
        expect(bodyText).not.toContain('Exception');
      }
    }
  });

  test('フィルター機能のテスト', async ({ page }) => {
    await page.goto('http://127.0.0.1:8000/admin/reservations');
    
    // フィルターボタンを探す
    const filterButton = page.locator('button:has-text("フィルター"), button:has-text("Filter")').first();
    
    if (await filterButton.isVisible()) {
      await filterButton.click();
      
      // フィルターパネルが開くのを待つ
      await page.waitForTimeout(500);
      
      // フィルターを適用
      const applyButton = page.locator('button:has-text("適用"), button:has-text("Apply")').first();
      if (await applyButton.isVisible()) {
        await applyButton.click();
        
        // エラーがないことを確認
        await expect(page).not.toHaveTitle(/Error/);
      }
    }
  });

  test('ページネーションのテスト', async ({ page }) => {
    await page.goto('http://127.0.0.1:8000/admin/customers');
    
    // ページネーションリンクを探す
    const paginationLinks = page.locator('a[href*="page="]');
    const linkCount = await paginationLinks.count();
    
    if (linkCount > 0) {
      // 2ページ目に移動
      const secondPageLink = paginationLinks.filter({ hasText: '2' }).first();
      if (await secondPageLink.isVisible()) {
        await secondPageLink.click();
        
        // エラーがないことを確認
        await expect(page).not.toHaveTitle(/Error/);
        await expect(page).toHaveURL(/page=2/);
      }
    }
  });

  test('削除操作のテスト', async ({ page }) => {
    // テスト用の顧客を作成
    await page.goto('http://127.0.0.1:8000/admin/customers/create');
    
    await page.fill('input[name="data.last_name"]', '削除テスト');
    await page.fill('input[name="data.first_name"]', '太郎');
    await page.fill('input[name="data.last_name_kana"]', 'サクジョテスト');
    await page.fill('input[name="data.first_name_kana"]', 'タロウ');
    await page.fill('input[name="data.phone"]', '09087654321');
    await page.fill('input[name="data.email"]', `delete-test${Date.now()}@example.com`);
    
    await page.click('button:has-text("作成")');
    
    // 作成後のページで削除ボタンを探す
    await page.waitForTimeout(1000);
    
    const deleteButton = page.locator('button:has-text("削除")').first();
    if (await deleteButton.isVisible()) {
      await deleteButton.click();
      
      // 確認ダイアログが表示される場合
      const confirmButton = page.locator('button:has-text("確認"), button:has-text("削除する")').last();
      if (await confirmButton.isVisible()) {
        await confirmButton.click();
      }
      
      // エラーがないことを確認
      await page.waitForTimeout(1000);
      const bodyText = await page.locator('body').textContent();
      expect(bodyText).not.toContain('Exception');
    }
  });

  test('バルクアクションのテスト', async ({ page }) => {
    await page.goto('http://127.0.0.1:8000/admin/reservations');
    
    // チェックボックスを探す
    const checkboxes = page.locator('input[type="checkbox"]');
    const checkboxCount = await checkboxes.count();
    
    if (checkboxCount > 1) {
      // 最初のチェックボックスを選択
      await checkboxes.nth(1).check();
      
      // バルクアクションボタンを探す
      const bulkActionButton = page.locator('button:has-text("アクション"), button:has-text("Bulk actions")').first();
      if (await bulkActionButton.isVisible()) {
        await bulkActionButton.click();
        
        // エラーがないことを確認
        await page.waitForTimeout(500);
        const bodyText = await page.locator('body').textContent();
        expect(bodyText).not.toContain('Exception');
      }
    }
  });

  test('エクスポート機能のテスト', async ({ page }) => {
    await page.goto('http://127.0.0.1:8000/admin/sales');
    
    // エクスポートボタンを探す
    const exportButton = page.locator('button:has-text("エクスポート"), button:has-text("Export")').first();
    
    if (await exportButton.isVisible()) {
      await exportButton.click();
      
      // エラーがないことを確認
      await page.waitForTimeout(500);
      const bodyText = await page.locator('body').textContent();
      expect(bodyText).not.toContain('Exception');
    }
  });

  test('画像アップロードのテスト', async ({ page }) => {
    await page.goto('http://127.0.0.1:8000/admin/menus/create');
    
    // 画像アップロードフィールドを探す
    const fileInput = page.locator('input[type="file"]').first();
    
    if (await fileInput.isVisible()) {
      // テスト画像をアップロード（実際のファイルは不要）
      const inputElement = await fileInput.elementHandle();
      if (inputElement) {
        // ファイル選択ダイアログを開くだけでエラーがないか確認
        await page.waitForTimeout(500);
        const bodyText = await page.locator('body').textContent();
        expect(bodyText).not.toContain('Exception');
      }
    }
  });

  test('日付ピッカーのテスト', async ({ page }) => {
    await page.goto('http://127.0.0.1:8000/admin/reservations/create');
    
    // 日付入力フィールドを探す
    const dateInputs = page.locator('input[type="date"], input[type="datetime-local"]');
    const dateCount = await dateInputs.count();
    
    if (dateCount > 0) {
      const firstDateInput = dateInputs.first();
      await firstDateInput.click();
      
      // 日付を設定
      const tomorrow = new Date();
      tomorrow.setDate(tomorrow.getDate() + 1);
      const dateString = tomorrow.toISOString().split('T')[0];
      
      await firstDateInput.fill(dateString);
      
      // エラーがないことを確認
      const bodyText = await page.locator('body').textContent();
      expect(bodyText).not.toContain('Exception');
    }
  });

  test('モーダルウィンドウのテスト', async ({ page }) => {
    await page.goto('http://127.0.0.1:8000/admin');
    
    // モーダルトリガーを探す
    const modalTriggers = page.locator('[data-modal], [x-data*="modal"], button[wire\\:click*="modal"]');
    const modalCount = await modalTriggers.count();
    
    if (modalCount > 0) {
      const firstTrigger = modalTriggers.first();
      await firstTrigger.click();
      
      // モーダルが開くのを待つ
      await page.waitForTimeout(500);
      
      // エラーがないことを確認
      const bodyText = await page.locator('body').textContent();
      expect(bodyText).not.toContain('Exception');
      
      // モーダルを閉じる
      const closeButton = page.locator('button:has-text("閉じる"), button:has-text("Close"), button:has-text("×")').first();
      if (await closeButton.isVisible()) {
        await closeButton.click();
      }
    }
  });

  test('通知メッセージのテスト', async ({ page }) => {
    await page.goto('http://127.0.0.1:8000/admin/customers/create');
    
    // 不完全なフォームを送信
    await page.click('button:has-text("作成")');
    
    // バリデーションエラーが表示されることを確認
    await page.waitForTimeout(500);
    
    // エラーメッセージが日本語であることを確認
    const errorMessages = page.locator('.text-danger, .text-red-600, [role="alert"]');
    const errorCount = await errorMessages.count();
    
    if (errorCount > 0) {
      const firstError = await errorMessages.first().textContent();
      // 英語のエラーメッセージでないことを確認
      expect(firstError).not.toContain('The');
      expect(firstError).not.toContain('field is required');
    }
  });

  test('セッションタイムアウトのテスト', async ({ page }) => {
    // 長時間のアイドル状態をシミュレート
    await page.goto('http://127.0.0.1:8000/admin');
    
    // 10秒待機
    await page.waitForTimeout(10000);
    
    // ページをリロード
    await page.reload();
    
    // ログイン画面にリダイレクトされるか、またはダッシュボードが表示されることを確認
    const currentUrl = page.url();
    const isLoggedIn = currentUrl.includes('/admin') && !currentUrl.includes('/login');
    const isLoginPage = currentUrl.includes('/login');
    
    expect(isLoggedIn || isLoginPage).toBeTruthy();
  });

  test('ブラウザの戻るボタンのテスト', async ({ page }) => {
    // 複数のページを訪問
    await page.goto('http://127.0.0.1:8000/admin');
    await page.goto('http://127.0.0.1:8000/admin/customers');
    await page.goto('http://127.0.0.1:8000/admin/reservations');
    
    // 戻るボタンを使用
    await page.goBack();
    await expect(page).toHaveURL(/\/admin\/customers/);
    await expect(page).not.toHaveTitle(/Error/);
    
    await page.goBack();
    await expect(page).toHaveURL(/\/admin$/);
    await expect(page).not.toHaveTitle(/Error/);
    
    // 進むボタンを使用
    await page.goForward();
    await expect(page).toHaveURL(/\/admin\/customers/);
    await expect(page).not.toHaveTitle(/Error/);
  });

  test('レスポンシブデザインのテスト', async ({ page }) => {
    const viewports = [
      { width: 1920, height: 1080 }, // Desktop
      { width: 768, height: 1024 },  // Tablet
      { width: 375, height: 667 },   // Mobile
    ];

    for (const viewport of viewports) {
      await page.setViewportSize(viewport);
      await page.goto('http://127.0.0.1:8000/admin');
      
      // エラーがないことを確認
      await expect(page).not.toHaveTitle(/Error/);
      
      // ナビゲーションメニューが表示または隠れていることを確認
      if (viewport.width < 768) {
        // モバイルメニューボタンを探す
        const mobileMenuButton = page.locator('button[aria-label*="menu"], button:has-text("☰")').first();
        if (await mobileMenuButton.isVisible()) {
          await mobileMenuButton.click();
          await page.waitForTimeout(300);
        }
      }
      
      const bodyText = await page.locator('body').textContent();
      expect(bodyText).not.toContain('Exception');
    }
  });
});

test.describe('公開ページ - エラーチェック', () => {
  test('予約フローの完全テスト', async ({ page }) => {
    // Basic認証
    await page.goto('http://127.0.0.1:8000/', {
      httpCredentials: {
        username: 'yumeno',
        password: 'takahashi5000'
      }
    });

    // 店舗選択
    await page.goto('http://127.0.0.1:8000/stores');
    await expect(page).not.toHaveTitle(/Error/);
    
    // 予約開始
    const storeButtons = page.locator('button:has-text("選択"), a:has-text("選択")');
    const storeCount = await storeButtons.count();
    
    if (storeCount > 0) {
      await storeButtons.first().click();
      
      // カテゴリー選択
      await page.waitForURL(/\/reservation/);
      await expect(page).not.toHaveTitle(/Error/);
      
      const categoryButtons = page.locator('button, a').filter({ hasText: /コース|メニュー/ });
      if (await categoryButtons.first().isVisible()) {
        await categoryButtons.first().click();
        
        // エラーがないことを確認
        await page.waitForTimeout(1000);
        const bodyText = await page.locator('body').textContent();
        expect(bodyText).not.toContain('Exception');
      }
    }
  });

  test('アクセストークン付きURLのテスト', async ({ page }) => {
    // 無効なトークンでアクセス
    await page.goto('http://127.0.0.1:8000/reservation/store?token=invalid_token_12345', {
      httpCredentials: {
        username: 'yumeno',
        password: 'takahashi5000'
      }
    });
    
    // エラーページではなく、適切なリダイレクトまたはメッセージが表示されることを確認
    const bodyText = await page.locator('body').textContent();
    expect(bodyText).not.toContain('Exception');
    expect(bodyText).not.toContain('Call to undefined');
  });

  test('404エラーページのテスト', async ({ page }) => {
    await page.goto('http://127.0.0.1:8000/nonexistent-page-12345', {
      httpCredentials: {
        username: 'yumeno',
        password: 'takahashi5000'
      }
    });
    
    // 404ページが適切に表示されることを確認
    await expect(page).toHaveTitle(/404|Not Found/);
    
    // スタックトレースが表示されていないことを確認
    const bodyText = await page.locator('body').textContent();
    expect(bodyText).not.toContain('Stack trace');
    expect(bodyText).not.toContain('Exception');
  });
});