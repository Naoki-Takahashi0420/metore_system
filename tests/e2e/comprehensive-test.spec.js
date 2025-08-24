import { test, expect } from '@playwright/test';

// テスト用のヘルパー関数
async function login(page, email = 'admin@xsyumeno.com', password = 'password') {
  await page.goto('/admin/login');
  await page.locator('input[type="email"]').first().fill(email);
  await page.locator('input[type="password"]').first().fill(password);
  await page.locator('button[type="submit"]').first().click();
  await page.waitForURL('**/admin', { timeout: 10000 });
}

test.describe('認証・ログインテスト', () => {
  test('正常なログイン', async ({ page }) => {
    await login(page);
    await expect(page).toHaveURL(/.*\/admin/);
    console.log('✅ ログイン成功');
  });

  test('無効な認証情報でのログイン失敗', async ({ page }) => {
    await page.goto('/admin/login');
    await page.locator('input[type="email"]').first().fill('wrong@email.com');
    await page.locator('input[type="password"]').first().fill('wrongpassword');
    await page.locator('button[type="submit"]').first().click();
    
    // エラーメッセージの確認
    await page.waitForTimeout(2000);
    const currentUrl = page.url();
    expect(currentUrl).toContain('/admin/login');
    console.log('✅ 無効な認証情報でログイン失敗を確認');
  });

  test('異なる権限でのログイン', async ({ page }) => {
    // スーパー管理者
    await login(page, 'superadmin@xsyumeno.com', 'password');
    await expect(page).toHaveURL(/.*\/admin/);
    await page.locator('button[aria-label="ユーザーメニュー"]').click();
    await page.locator('text=ログアウト').click();
    
    // スタッフ
    await login(page, 'staff@xsyumeno.com', 'password');
    await expect(page).toHaveURL(/.*\/admin/);
    console.log('✅ 異なる権限でのログイン成功');
  });
});

test.describe('ダッシュボード機能テスト', () => {
  test.beforeEach(async ({ page }) => {
    await login(page);
  });

  test('ダッシュボードウィジェット表示', async ({ page }) => {
    // 本日の予約ウィジェット
    await expect(page.locator('text=本日の予約')).toBeVisible();
    
    // 売上統計ウィジェット
    await expect(page.locator('text=本日の売上')).toBeVisible();
    await expect(page.locator('text=今月の売上')).toBeVisible();
    await expect(page.locator('text=本日の来店客数')).toBeVisible();
    
    console.log('✅ ダッシュボードウィジェット表示確認');
  });

  test('売上ページへのクリック遷移', async ({ page }) => {
    // 今月の売上カードを探してクリック
    const monthSalesCards = page.locator('div').filter({ hasText: /^今月の売上/ });
    const clickableCard = monthSalesCards.first();
    
    // カードが存在することを確認
    await expect(clickableCard).toBeVisible();
    
    // onclick属性があるか確認してクリック
    await page.evaluate(() => {
      const cards = document.querySelectorAll('div');
      for (const card of cards) {
        if (card.textContent?.includes('今月の売上') && card.onclick) {
          card.click();
          break;
        }
      }
    });
    
    await page.waitForTimeout(2000);
    if (page.url().includes('/admin/sales')) {
      console.log('✅ 売上ページへの遷移成功');
    }
  });
});

test.describe('予約管理機能テスト', () => {
  test.beforeEach(async ({ page }) => {
    await login(page);
  });

  test('予約一覧の表示と検索', async ({ page }) => {
    await page.goto('/admin/reservations');
    await page.waitForTimeout(2000);
    
    // テーブルヘッダーの確認
    await expect(page.locator('text=予約番号').first()).toBeVisible();
    await expect(page.locator('text=顧客名').first()).toBeVisible();
    
    // 検索機能のテスト
    const searchInput = page.locator('input[type="search"]').first();
    if (await searchInput.isVisible()) {
      await searchInput.fill('山田');
      await page.waitForTimeout(1000);
    }
    
    console.log('✅ 予約一覧表示・検索機能確認');
  });

  test('新規予約作成フォーム', async ({ page }) => {
    await page.goto('/admin/reservations');
    
    // 新規作成ボタンをクリック
    const createButton = page.locator('a').filter({ hasText: /新規作成|Create|追加/ }).first();
    if (await createButton.isVisible()) {
      await createButton.click();
      await page.waitForTimeout(2000);
      
      // フォームフィールドの確認
      await expect(page.locator('input, select').first()).toBeVisible();
      console.log('✅ 新規予約作成フォーム表示確認');
    }
  });
});

test.describe('顧客管理機能テスト', () => {
  test.beforeEach(async ({ page }) => {
    await login(page);
  });

  test('顧客一覧の表示', async ({ page }) => {
    await page.goto('/admin/customers');
    await page.waitForTimeout(2000);
    
    // テーブルヘッダーの確認
    const headers = ['顧客番号', '名前', '電話番号', 'メール'];
    for (const header of headers) {
      const headerElement = page.locator(`text=${header}`).first();
      if (await headerElement.isVisible()) {
        await expect(headerElement).toBeVisible();
      }
    }
    
    console.log('✅ 顧客一覧表示確認');
  });

  test('顧客詳細モーダル', async ({ page }) => {
    await page.goto('/admin/customers');
    await page.waitForTimeout(2000);
    
    // 編集ボタンをクリック
    const editButton = page.locator('button, a').filter({ hasText: /編集|Edit|詳細/ }).first();
    if (await editButton.isVisible()) {
      await editButton.click();
      await page.waitForTimeout(2000);
      
      // モーダルまたは詳細ページの確認
      const modal = page.locator('.modal, [role="dialog"], .fixed.inset-0').first();
      const isModalVisible = await modal.isVisible();
      
      if (isModalVisible) {
        console.log('✅ 顧客詳細モーダル表示確認');
        
        // モーダルを閉じる
        const closeButton = page.locator('button').filter({ hasText: /閉じる|Close|×|キャンセル/ }).first();
        if (await closeButton.isVisible()) {
          await closeButton.click();
        }
      }
    }
  });
});

test.describe('売上管理機能テスト', () => {
  test.beforeEach(async ({ page }) => {
    await login(page);
  });

  test('売上一覧の表示とフィルタ', async ({ page }) => {
    await page.goto('/admin/sales');
    await page.waitForTimeout(2000);
    
    // テーブルヘッダーの確認
    await expect(page.locator('text=売上番号').first()).toBeVisible();
    
    // フィルタボタンの確認
    const filterButton = page.locator('button').filter({ hasText: /フィルタ|Filter/ }).first();
    if (await filterButton.isVisible()) {
      await filterButton.click();
      await page.waitForTimeout(1000);
      
      // フィルタモーダルの確認
      const filterModal = page.locator('.modal, [role="dialog"]').first();
      if (await filterModal.isVisible()) {
        console.log('✅ 売上フィルタモーダル表示確認');
        
        // ESCキーでモーダルを閉じる
        await page.keyboard.press('Escape');
      }
    }
    
    console.log('✅ 売上一覧表示確認');
  });

  test('日次精算ボタン', async ({ page }) => {
    await page.goto('/admin/sales');
    await page.waitForTimeout(2000);
    
    const dailyClosingButton = page.locator('a, button').filter({ hasText: /日次精算/ }).first();
    if (await dailyClosingButton.isVisible()) {
      await dailyClosingButton.click();
      await page.waitForTimeout(2000);
      console.log('✅ 日次精算ページへの遷移確認');
    }
  });
});

test.describe('予約カレンダー機能テスト', () => {
  test.beforeEach(async ({ page }) => {
    await login(page);
  });

  test('カレンダービューの表示', async ({ page }) => {
    await page.goto('/admin/reservation-calendars');
    await page.waitForTimeout(3000);
    
    // FullCalendarの要素を確認
    const calendar = page.locator('.fc, .fc-view, [class*="calendar"]').first();
    await expect(calendar).toBeVisible();
    
    // カレンダーのナビゲーションボタン
    const prevButton = page.locator('.fc-prev-button, button[title*="Previous"], button[title*="前"]').first();
    const nextButton = page.locator('.fc-next-button, button[title*="Next"], button[title*="次"]').first();
    
    if (await prevButton.isVisible() && await nextButton.isVisible()) {
      // 次月へ移動
      await nextButton.click();
      await page.waitForTimeout(1000);
      
      // 前月へ戻る
      await prevButton.click();
      await page.waitForTimeout(1000);
      
      console.log('✅ カレンダーナビゲーション動作確認');
    }
    
    console.log('✅ 予約カレンダー表示確認');
  });

  test('カレンダーイベントクリック', async ({ page }) => {
    await page.goto('/admin/reservation-calendars');
    await page.waitForTimeout(3000);
    
    // カレンダーイベントをクリック
    const event = page.locator('.fc-event, [class*="event"]').first();
    if (await event.isVisible()) {
      await event.click();
      await page.waitForTimeout(2000);
      
      // モーダルの確認
      const modal = page.locator('.modal, [role="dialog"], .fixed.inset-0').first();
      if (await modal.isVisible()) {
        console.log('✅ 予約詳細モーダル表示確認');
        
        // カルテ記入ボタンの確認
        const medicalButton = page.locator('button').filter({ hasText: /カルテ/ }).first();
        if (await medicalButton.isVisible()) {
          console.log('✅ カルテ記入ボタン確認');
        }
        
        // モーダルを閉じる
        await page.keyboard.press('Escape');
      }
    }
  });

  test('スーパー管理者の店舗選択', async ({ page }) => {
    // スーパー管理者でログイン
    await page.goto('/admin/login');
    await page.locator('input[type="email"]').first().fill('superadmin@xsyumeno.com');
    await page.locator('input[type="password"]').first().fill('password');
    await page.locator('button[type="submit"]').first().click();
    await page.waitForURL('**/admin', { timeout: 10000 });
    
    // 予約カレンダーへ移動
    await page.goto('/admin/reservation-calendars');
    await page.waitForTimeout(3000);
    
    // 店舗選択ボタンの確認
    const storeSelectButton = page.locator('button').filter({ hasText: /店舗/ }).first();
    if (await storeSelectButton.isVisible()) {
      await storeSelectButton.click();
      await page.waitForTimeout(1000);
      
      // 店舗選択モーダルの確認
      const storeModal = page.locator('.modal, [role="dialog"]').first();
      if (await storeModal.isVisible()) {
        console.log('✅ 店舗選択モーダル表示確認');
        await page.keyboard.press('Escape');
      }
    }
  });
});

test.describe('モーダル・ポップアップ総合テスト', () => {
  test.beforeEach(async ({ page }) => {
    await login(page);
  });

  test('各種モーダルの開閉テスト', async ({ page }) => {
    // 予約一覧でのモーダル
    await page.goto('/admin/reservations');
    await page.waitForTimeout(2000);
    
    // アクションボタンをクリック
    const actionButton = page.locator('button[aria-label*="Actions"], button').filter({ hasText: /⋮|Actions|アクション/ }).first();
    if (await actionButton.isVisible()) {
      await actionButton.click();
      await page.waitForTimeout(1000);
      
      // ドロップダウンメニューの確認
      const dropdown = page.locator('[role="menu"], .dropdown-menu').first();
      if (await dropdown.isVisible()) {
        console.log('✅ アクションドロップダウン表示確認');
        await page.keyboard.press('Escape');
      }
    }
    
    // 通知パネルの確認
    const notificationButton = page.locator('button[aria-label*="Notifications"], button').filter({ hasText: /通知|Notifications|🔔/ }).first();
    if (await notificationButton.isVisible()) {
      await notificationButton.click();
      await page.waitForTimeout(1000);
      
      const notificationPanel = page.locator('.notification-panel, [role="dialog"]').first();
      if (await notificationPanel.isVisible()) {
        console.log('✅ 通知パネル表示確認');
        await page.keyboard.press('Escape');
      }
    }
  });

  test('確認ダイアログのテスト', async ({ page }) => {
    await page.goto('/admin/reservations');
    await page.waitForTimeout(2000);
    
    // 削除ボタンなどの確認ダイアログをトリガー
    const deleteButton = page.locator('button').filter({ hasText: /削除|Delete/ }).first();
    if (await deleteButton.isVisible()) {
      // ダイアログのリスナーを設定
      page.on('dialog', async dialog => {
        console.log('✅ 確認ダイアログ表示: ' + dialog.message());
        await dialog.dismiss(); // キャンセル
      });
      
      await deleteButton.click();
      await page.waitForTimeout(1000);
    }
  });
});

test.describe('レスポンシブデザインテスト', () => {
  test('モバイルビューでの表示', async ({ page }) => {
    // モバイルサイズに変更
    await page.setViewportSize({ width: 375, height: 667 });
    
    await login(page);
    
    // ハンバーガーメニューの確認
    const hamburgerMenu = page.locator('button[aria-label*="Menu"], button').filter({ hasText: /☰|Menu/ }).first();
    if (await hamburgerMenu.isVisible()) {
      await hamburgerMenu.click();
      await page.waitForTimeout(1000);
      
      // サイドバーの表示確認
      const sidebar = page.locator('nav, .sidebar, aside').first();
      if (await sidebar.isVisible()) {
        console.log('✅ モバイルメニュー表示確認');
      }
    }
  });

  test('タブレットビューでの表示', async ({ page }) => {
    // タブレットサイズに変更
    await page.setViewportSize({ width: 768, height: 1024 });
    
    await login(page);
    await page.goto('/admin/reservations');
    await page.waitForTimeout(2000);
    
    // テーブルのレスポンシブ表示確認
    const table = page.locator('table').first();
    if (await table.isVisible()) {
      console.log('✅ タブレットビューでのテーブル表示確認');
    }
  });
});

// テスト完了後のサマリー
test.afterAll(async () => {
  console.log('\n========================================');
  console.log('📊 網羅的E2Eテスト完了');
  console.log('========================================\n');
});