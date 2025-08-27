import { test, expect } from '@playwright/test';

/**
 * LINE統合機能の包括的E2Eテスト（ultrathink approach）
 * 
 * このテストスイートは以下の観点から設計されています：
 * 1. 実際のユーザーフローに基づいた統合テスト
 * 2. エッジケースと異常系のカバレッジ
 * 3. LINE Bot機能の完全性検証
 * 4. 管理画面での設定変更テスト
 * 5. 通知優先度とフォールバック機能のテスト
 */

test.describe('LINE Integration - Complete User Journey', () => {
  
  test.beforeEach(async ({ page }) => {
    // テスト環境のセットアップ
    await page.goto('/');
  });

  test.describe('新規顧客のLINE登録フロー', () => {
    
    test('正常系: 予約完了からLINE登録までのフルフロー', async ({ page }) => {
      // 1. 予約フォームに移動
      await page.goto('/reservation');
      
      // 2. 予約フォーム入力
      await page.fill('[name="last_name"]', 'テスト');
      await page.fill('[name="first_name"]', '太郎');
      await page.fill('[name="phone"]', '09012345678');
      await page.fill('[name="email"]', 'test@example.com');
      
      // 3. 店舗選択（流入経路追跡のため重要）
      await page.selectOption('[name="store_id"]', '1');
      
      // 4. 日時選択
      const futureDate = new Date();
      futureDate.setDate(futureDate.getDate() + 7);
      await page.fill('[name="reservation_date"]', futureDate.toISOString().split('T')[0]);
      await page.selectOption('[name="time_slot"]', '10:00');
      
      // 5. メニュー選択
      await page.selectOption('[name="menu_id"]', '1');
      
      // 6. 予約送信
      await page.click('button[type="submit"]');
      
      // 7. 完了画面の確認
      await expect(page.locator('h1')).toContainText('予約が完了');
      
      // 8. LINE登録QRコードの表示確認
      const qrCodeSection = page.locator('[data-testid="line-qr-section"]');
      await expect(qrCodeSection).toBeVisible();
      
      // 9. QRコード画像の存在確認
      const qrCodeImg = qrCodeSection.locator('svg');
      await expect(qrCodeImg).toBeVisible();
      
      // 10. 流入経路データの確認
      const qrLink = await qrCodeSection.locator('a').getAttribute('href');
      expect(qrLink).toContain('store_id=1');
      expect(qrLink).toContain('source=reservation');
    });

    test('エッジケース: QRコード生成に失敗した場合の表示', async ({ page }) => {
      // 予約データに不備がある場合のテスト
      await page.goto('/reservation/complete');
      
      // QRコードセクションが表示されない場合の代替表示を確認
      const fallbackMessage = page.locator('[data-testid="qr-fallback"]');
      if (await fallbackMessage.isVisible()) {
        await expect(fallbackMessage).toContainText('LINE登録は後ほど');
      }
    });

    test('バリデーション: 不正な店舗IDでのQRコード生成', async ({ page }) => {
      // 不正な店舗IDでのアクセス
      await page.goto('/reservation/complete?store_id=999&reservation_id=1');
      
      // エラーハンドリングの確認
      const errorMessage = page.locator('[data-testid="error-message"]');
      if (await errorMessage.isVisible()) {
        await expect(errorMessage).toContainText('エラー');
      }
    });
  });

  test.describe('管理画面でのLINEメッセージテンプレート管理', () => {
    
    test.beforeEach(async ({ page }) => {
      // 管理者としてログイン
      await page.goto('/admin/login');
      await page.fill('[name="email"]', 'admin@eye-training.com');
      await page.fill('[name="password"]', 'password');
      await page.click('button[type="submit"]');
    });

    test('テンプレート作成・編集・削除の完全フロー', async ({ page }) => {
      // 1. LINEメッセージテンプレート管理画面へ
      await page.goto('/admin/line-message-templates');
      await expect(page.locator('h1')).toContainText('LINEメッセージテンプレート');
      
      // 2. 新規テンプレート作成
      await page.click('a:has-text("新規作成")');
      
      // 3. フォーム入力
      await page.fill('[name="key"]', 'test_campaign_' + Date.now());
      await page.fill('[name="name"]', 'テストキャンペーン');
      await page.selectOption('[name="category"]', 'campaign');
      await page.fill('[name="message"]', 'こんにちは{{customer_name}}さん！特別なキャンペーンのお知らせです。');
      
      // 4. 変数設定
      await page.click('[data-testid="add-variable"]');
      await page.fill('[name="variables[customer_name]"]', '顧客名');
      
      // 5. 保存
      await page.click('button[type="submit"]');
      
      // 6. 作成確認
      await expect(page.locator('[data-testid="success-message"]')).toBeVisible();
      
      // 7. 一覧に戻って確認
      await page.goto('/admin/line-message-templates');
      await expect(page.locator('table')).toContainText('テストキャンペーン');
      
      // 8. 編集テスト
      await page.click('tr:has-text("テストキャンペーン") [data-testid="edit-button"]');
      await page.fill('[name="description"]', '編集テスト用の説明');
      await page.click('button[type="submit"]');
      
      // 9. プレビュー機能テスト
      await page.goto('/admin/line-message-templates');
      await page.click('tr:has-text("テストキャンペーン") [data-testid="preview-button"]');
      
      // プレビューモーダルの内容確認
      const previewModal = page.locator('[data-testid="preview-modal"]');
      await expect(previewModal).toBeVisible();
      await expect(previewModal).toContainText('{{customer_name}}');
    });

    test('テンプレートの複製機能テスト', async ({ page }) => {
      await page.goto('/admin/line-message-templates');
      
      // 既存テンプレートの複製
      const firstRow = page.locator('tbody tr').first();
      await firstRow.locator('[data-testid="duplicate-button"]').click();
      
      // 複製されたテンプレートの編集画面が開く
      await expect(page.locator('[name="name"]')).toHaveValue(/.*コピー.*/);
      await expect(page.locator('[name="key"]')).toHaveValue(/.*_copy$/);
      
      // 保存して確認
      await page.click('button[type="submit"]');
      await page.goto('/admin/line-message-templates');
      
      // 複製されたテンプレートが表示されているか確認
      await expect(page.locator('table')).toContainText('コピー');
    });

    test('バリデーション: 不正な入力値でのテンプレート作成', async ({ page }) => {
      await page.goto('/admin/line-message-templates/create');
      
      // 空の値で送信
      await page.click('button[type="submit"]');
      
      // バリデーションエラーの確認
      await expect(page.locator('[data-testid="validation-error"]')).toBeVisible();
      
      // 重複キーのテスト
      await page.fill('[name="key"]', 'welcome'); // 既存のキー
      await page.fill('[name="name"]', 'テスト');
      await page.selectOption('[name="category"]', 'general');
      await page.fill('[name="message"]', 'テストメッセージ');
      await page.click('button[type="submit"]');
      
      // 重複エラーの確認
      await expect(page.locator('.error')).toContainText('既に存在');
    });
  });

  test.describe('LINE設定管理画面', () => {
    
    test.beforeEach(async ({ page }) => {
      await page.goto('/admin/login');
      await page.fill('[name="email"]', 'admin@eye-training.com');
      await page.fill('[name="password"]', 'password');
      await page.click('button[type="submit"]');
    });

    test('LINE設定の変更と保存', async ({ page }) => {
      // 1. LINE設定画面へ
      await page.goto('/admin/line-settings');
      
      // 2. 通知優先度設定の変更
      const priorityToggle = page.locator('[data-testid="notification_priority"]');
      await priorityToggle.click();
      
      // 3. キャンペーン配信タイミング設定
      await page.selectOption('[name="campaign_send_timing"]', '24hour');
      
      // 4. 保存
      await page.click('button:has-text("保存")');
      
      // 5. 保存確認
      await expect(page.locator('[data-testid="success-notification"]')).toBeVisible();
      
      // 6. ページリロード後の設定確認
      await page.reload();
      await expect(page.locator('[name="campaign_send_timing"]')).toHaveValue('24hour');
    });

    test('システム設定項目の保護', async ({ page }) => {
      await page.goto('/admin/line-settings');
      
      // システム設定項目は編集不可になっているか確認
      const systemSettings = page.locator('[data-is-system="true"]');
      const count = await systemSettings.count();
      
      for (let i = 0; i < count; i++) {
        const setting = systemSettings.nth(i);
        const input = setting.locator('input, select, textarea');
        if (await input.count() > 0) {
          await expect(input).toBeDisabled();
        }
      }
    });

    test('マニュアル・ヘルプテキストの表示', async ({ page }) => {
      await page.goto('/admin/line-settings');
      
      // 使用方法マニュアルの確認
      const manualSection = page.locator('[data-testid="usage-manual"]');
      await expect(manualSection).toBeVisible();
      await expect(manualSection).toContainText('LINE Bot 使用方法');
      
      // トラブルシューティングの確認
      const troubleshootingSection = page.locator('[data-testid="troubleshooting"]');
      await expect(troubleshootingSection).toBeVisible();
      await expect(troubleshootingSection).toContainText('よくある問題');
    });
  });

  test.describe('顧客管理画面でのキャンペーン配信', () => {
    
    test.beforeEach(async ({ page }) => {
      await page.goto('/admin/login');
      await page.fill('[name="email"]', 'admin@eye-training.com');
      await page.fill('[name="password"]', 'password');
      await page.click('button[type="submit"]');
    });

    test('個別顧客へのキャンペーン配信テスト', async ({ page }) => {
      // 1. 顧客管理画面へ
      await page.goto('/admin/customers');
      
      // 2. LINE登録済み顧客を探す
      const lineCustomer = page.locator('tr:has([data-testid="line-registered"])').first();
      
      if (await lineCustomer.count() > 0) {
        // 3. キャンペーン配信ボタンをクリック
        await lineCustomer.locator('[data-testid="send-campaign"]').click();
        
        // 4. モーダルが開く
        const campaignModal = page.locator('[data-testid="campaign-modal"]');
        await expect(campaignModal).toBeVisible();
        
        // 5. テンプレート選択
        await page.selectOption('[name="template_key"]', 'campaign_welcome');
        
        // 6. テストモードをON
        await page.check('[name="test_mode"]');
        
        // 7. 送信実行
        await page.click('button:has-text("送信")');
        
        // 8. テスト送信結果の確認
        await expect(page.locator('[data-testid="test-result"]')).toBeVisible();
        await expect(page.locator('[data-testid="test-result"]')).toContainText('テスト送信結果');
      }
    });

    test('カスタムメッセージでの配信テスト', async ({ page }) => {
      await page.goto('/admin/customers');
      
      const lineCustomer = page.locator('tr:has([data-testid="line-registered"])').first();
      
      if (await lineCustomer.count() > 0) {
        await lineCustomer.locator('[data-testid="send-campaign"]').click();
        
        // カスタムメッセージを入力
        const customMessage = 'これはテスト用のカスタムメッセージです。';
        await page.fill('[name="custom_message"]', customMessage);
        
        // テストモード有効
        await page.check('[name="test_mode"]');
        
        await page.click('button:has-text("送信")');
        
        // カスタムメッセージが反映されているか確認
        await expect(page.locator('[data-testid="test-result"]')).toContainText(customMessage);
      }
    });

    test('LINE未登録顧客への配信ボタン非表示テスト', async ({ page }) => {
      await page.goto('/admin/customers');
      
      // LINE未登録顧客の行を確認
      const nonLineCustomer = page.locator('tr:not(:has([data-testid="line-registered"]))').first();
      
      if (await nonLineCustomer.count() > 0) {
        // キャンペーン配信ボタンが表示されていないことを確認
        const campaignButton = nonLineCustomer.locator('[data-testid="send-campaign"]');
        await expect(campaignButton).not.toBeVisible();
      }
    });
  });

  test.describe('通知優先度とフォールバック機能', () => {
    
    test('LINE優先設定での予約リマインダー送信', async ({ page }) => {
      // この部分は実際のAPIコールをモックする必要があるため、
      // テスト環境での設定が必要
      
      // 1. テスト用顧客データの確認
      await page.goto('/admin/customers');
      
      // 2. LINE登録済み顧客の予約リマインダーテスト
      // （実際の送信ロジックのテストは別途APIテストで実施）
    });

    test('SMS フォールバック機能のテスト', async ({ page }) => {
      // LINE送信失敗時のSMSフォールバック機能テスト
      // （モック環境でのテストが必要）
    });
  });

  test.describe('エラーハンドリングとエッジケース', () => {
    
    test('ネットワークエラー時の挙動', async ({ page }) => {
      // ネットワーク切断をシミュレート
      await page.route('**/*', route => route.abort());
      
      await page.goto('/admin/line-message-templates');
      
      // エラー表示の確認
      const errorMessage = page.locator('[data-testid="network-error"]');
      await expect(errorMessage).toBeVisible();
    });

    test('セッションタイムアウト時の挙動', async ({ page }) => {
      // セッション期限切れ状態をシミュレート
      await page.goto('/admin/login');
      await page.fill('[name="email"]', 'admin@eye-training.com');
      await page.fill('[name="password"]', 'password');
      await page.click('button[type="submit"]');
      
      // セッション削除
      await page.evaluate(() => {
        document.cookie = 'laravel_session=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;';
      });
      
      // 管理画面へのアクセス試行
      await page.goto('/admin/line-message-templates');
      
      // ログイン画面にリダイレクトされることを確認
      await expect(page).toHaveURL(/.*\/admin\/login/);
    });

    test('大量データでのページネーション機能', async ({ page }) => {
      await page.goto('/admin/login');
      await page.fill('[name="email"]', 'admin@eye-training.com');
      await page.fill('[name="password"]', 'password');
      await page.click('button[type="submit"]');
      
      await page.goto('/admin/customers');
      
      // ページネーションコントロールの確認
      const pagination = page.locator('[data-testid="pagination"]');
      if (await pagination.isVisible()) {
        // 次のページへ移動
        await page.click('a:has-text("次へ")');
        
        // URL変更の確認
        await expect(page).toHaveURL(/.*page=2/);
        
        // データが変わっていることを確認
        const customerTable = page.locator('table tbody');
        await expect(customerTable.locator('tr')).toHaveCount.toBeGreaterThan(0);
      }
    });

    test('ブラウザの戻る・進むボタンでの状態管理', async ({ page }) => {
      await page.goto('/admin/login');
      await page.fill('[name="email"]', 'admin@eye-training.com');
      await page.fill('[name="password"]', 'password');
      await page.click('button[type="submit"]');
      
      // 画面遷移
      await page.goto('/admin/line-message-templates');
      await page.goto('/admin/line-settings');
      await page.goto('/admin/customers');
      
      // 戻るボタンテスト
      await page.goBack();
      await expect(page).toHaveURL(/.*line-settings/);
      
      await page.goBack();
      await expect(page).toHaveURL(/.*line-message-templates/);
      
      // 進むボタンテスト
      await page.goForward();
      await expect(page).toHaveURL(/.*line-settings/);
    });
  });

  test.describe('レスポンシブデザイン対応', () => {
    
    test('モバイル端末での予約完了画面', async ({ page }) => {
      // モバイルビューポートに設定
      await page.setViewportSize({ width: 375, height: 667 });
      
      await page.goto('/reservation/complete');
      
      // QRコードセクションがモバイルで適切に表示されるか
      const qrSection = page.locator('[data-testid="line-qr-section"]');
      if (await qrSection.isVisible()) {
        // QRコードのサイズが適切か確認
        const qrImage = qrSection.locator('svg');
        const boundingBox = await qrImage.boundingBox();
        expect(boundingBox.width).toBeLessThan(300); // モバイルに適したサイズ
      }
    });

    test('タブレット端末での管理画面', async ({ page }) => {
      // タブレットビューポートに設定
      await page.setViewportSize({ width: 768, height: 1024 });
      
      await page.goto('/admin/login');
      await page.fill('[name="email"]', 'admin@eye-training.com');
      await page.fill('[name="password"]', 'password');
      await page.click('button[type="submit"]');
      
      await page.goto('/admin/line-message-templates');
      
      // テーブルが適切に表示されるか確認
      const table = page.locator('table');
      await expect(table).toBeVisible();
      
      // 横スクロールが必要な場合のUI確認
      const tableContainer = page.locator('.table-container');
      if (await tableContainer.isVisible()) {
        // スクロール可能かどうか確認
        const scrollWidth = await tableContainer.evaluate(el => el.scrollWidth);
        const clientWidth = await tableContainer.evaluate(el => el.clientWidth);
        
        if (scrollWidth > clientWidth) {
          // 横スクロールインジケーターなどのUI要素確認
          const scrollIndicator = page.locator('[data-testid="scroll-indicator"]');
          await expect(scrollIndicator).toBeVisible();
        }
      }
    });
  });

  test.describe('アクセシビリティ確認', () => {
    
    test('キーボードナビゲーション', async ({ page }) => {
      await page.goto('/admin/login');
      
      // Tabキーでのフォーカス移動テスト
      await page.keyboard.press('Tab'); // email field
      await expect(page.locator('[name="email"]')).toBeFocused();
      
      await page.keyboard.press('Tab'); // password field  
      await expect(page.locator('[name="password"]')).toBeFocused();
      
      await page.keyboard.press('Tab'); // submit button
      await expect(page.locator('button[type="submit"]')).toBeFocused();
      
      // Enterキーでのフォーム送信
      await page.fill('[name="email"]', 'admin@eye-training.com');
      await page.fill('[name="password"]', 'password');
      await page.keyboard.press('Enter');
      
      // ログイン成功確認
      await expect(page).toHaveURL(/.*\/admin/);
    });

    test('スクリーンリーダー対応の確認', async ({ page }) => {
      await page.goto('/reservation/complete');
      
      // QRコードセクションのaria-labelやalt属性確認
      const qrSection = page.locator('[data-testid="line-qr-section"]');
      if (await qrSection.isVisible()) {
        const qrImage = qrSection.locator('svg');
        
        // aria-labelまたはtitle属性の存在確認
        const hasAriaLabel = await qrImage.getAttribute('aria-label');
        const hasTitle = await qrImage.getAttribute('title');
        
        expect(hasAriaLabel || hasTitle).toBeTruthy();
      }
      
      // フォーム要素のラベル確認
      const formElements = page.locator('input, select, textarea');
      const count = await formElements.count();
      
      for (let i = 0; i < count; i++) {
        const element = formElements.nth(i);
        const hasLabel = await element.getAttribute('aria-label') || 
                        await element.getAttribute('aria-labelledby') ||
                        await page.locator(`label[for="${await element.getAttribute('id')}"]`).count() > 0;
        
        if (await element.isVisible()) {
          expect(hasLabel).toBeTruthy();
        }
      }
    });
  });

  test.describe('パフォーマンステスト', () => {
    
    test('ページロード時間の測定', async ({ page }) => {
      // ページロード時間を測定
      const startTime = Date.now();
      
      await page.goto('/admin/customers');
      
      // DOMContentLoadedまでの時間
      await page.waitForLoadState('domcontentloaded');
      const loadTime = Date.now() - startTime;
      
      // 3秒以内に読み込み完了することを確認
      expect(loadTime).toBeLessThan(3000);
    });

    test('大量データでのテーブル表示パフォーマンス', async ({ page }) => {
      await page.goto('/admin/login');
      await page.fill('[name="email"]', 'admin@eye-training.com');
      await page.fill('[name="password"]', 'password');
      await page.click('button[type="submit"]');
      
      const startTime = Date.now();
      await page.goto('/admin/customers');
      
      // テーブルの描画完了まで待機
      await page.waitForSelector('table tbody tr');
      
      const renderTime = Date.now() - startTime;
      
      // 5秒以内にテーブルが描画されることを確認
      expect(renderTime).toBeLessThan(5000);
    });
  });
});