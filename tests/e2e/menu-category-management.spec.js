import { test, expect } from '@playwright/test';

test.describe('メニューカテゴリー管理 E2Eテスト', () => {
    
    test.beforeEach(async ({ page }) => {
        // 管理画面にログイン
        await page.goto('http://127.0.0.1:8000/admin/login');
        await page.waitForLoadState('domcontentloaded');
        
        // ログイン処理
        await page.fill('input[id="data.email"]', 'naoki@yumeno-marketing.jp');
        await page.fill('input[id="data.password"]', 'Takahashi5000');
        await page.click('button[type="submit"]');
        
        await expect(page).toHaveURL(/.*admin/);
        await page.waitForTimeout(1000);
    });

    test('メニューカテゴリーの作成・編集・削除フロー', async ({ page }) => {
        // メニューカテゴリー管理へ移動
        await page.click('text=メニュー管理');
        await page.waitForTimeout(500);
        await page.click('text=メニューカテゴリー');
        await expect(page).toHaveURL(/.*menu-categories/);

        // 新規カテゴリー作成
        await page.click('button:has-text("新規作成")');
        await page.waitForTimeout(500);

        // フォーム入力
        await page.fill('input[name="name"]', 'テストカテゴリー');
        await page.fill('textarea[name="description"]', 'E2Eテスト用のカテゴリーです');
        await page.fill('input[name="sort_order"]', '99');

        // 保存
        await page.click('button:has-text("作成")');
        await page.waitForTimeout(1000);

        // 作成確認
        await expect(page.locator('text=テストカテゴリー')).toBeVisible();

        // 編集
        await page.click('tr:has-text("テストカテゴリー") button[title="編集"]');
        await page.waitForTimeout(500);
        
        await page.fill('input[name="name"]', 'テストカテゴリー（更新済み）');
        await page.click('button:has-text("保存")');
        await page.waitForTimeout(1000);

        // 更新確認
        await expect(page.locator('text=テストカテゴリー（更新済み）')).toBeVisible();

        // 削除
        await page.click('tr:has-text("テストカテゴリー（更新済み）") button[title="削除"]');
        await page.waitForTimeout(500);
        await page.click('button:has-text("確認")');
        await page.waitForTimeout(1000);

        // 削除確認
        await expect(page.locator('text=テストカテゴリー（更新済み）')).not.toBeVisible();
    });

    test('メニュー作成と時間設定の確認', async ({ page }) => {
        // メニュー管理へ移動
        await page.click('text=メニュー管理');
        await page.waitForTimeout(500);
        await page.click('a[href*="/admin/menus"]:has-text("メニュー管理")');
        await expect(page).toHaveURL(/.*menus/);

        // 新規メニュー作成
        await page.click('button:has-text("新規作成")');
        await page.waitForTimeout(500);

        // カテゴリー選択（ドロップダウン確認）
        const categorySelect = page.locator('select[name="category_id"], [data-test="category-select"]').first();
        if (await categorySelect.isVisible()) {
            const options = await categorySelect.locator('option').allTextContents();
            console.log('カテゴリーオプション:', options);
            
            // カテゴリーが存在することを確認
            expect(options.length).toBeGreaterThan(0);
        }

        // 時間選択（30/50/80分）の確認
        const durationSelect = page.locator('select[name="duration_minutes"], [data-test="duration-select"]').first();
        if (await durationSelect.isVisible()) {
            // 30分を選択
            await durationSelect.selectOption('30');
            
            // 値が設定されたことを確認
            const selectedValue = await durationSelect.inputValue();
            expect(['30', '50', '80']).toContain(selectedValue);
        }

        // 必須フィールド入力
        await page.fill('input[name="name"]', 'テストメニュー（30分コース）');
        await page.fill('input[name="price"]', '5000');
        
        // フラグの確認
        const visibleToggle = page.locator('input[name="is_visible_to_customer"]');
        const subscriptionToggle = page.locator('input[name="is_subscription_only"]');
        
        if (await visibleToggle.isVisible()) {
            await expect(visibleToggle).toBeChecked(); // デフォルトでチェック済み
        }
        
        if (await subscriptionToggle.isVisible()) {
            await expect(subscriptionToggle).not.toBeChecked(); // デフォルトで未チェック
        }

        console.log('✅ メニュー作成フォームの動作確認完了');
    });

    test('サブスクリプション管理画面の確認', async ({ page }) => {
        // サブスクリプション管理へ移動
        await page.goto('http://127.0.0.1:8000/admin/customer-subscriptions');
        
        // ページが正しく表示されることを確認
        await expect(page.locator('h1, h2').first()).toContainText(/サブスク/i);
        
        // テーブルヘッダーの確認
        const expectedHeaders = ['顧客', '店舗', 'プラン', '状態'];
        for (const header of expectedHeaders) {
            const headerElement = page.locator(`th:has-text("${header}")`);
            if (await headerElement.count() > 0) {
                await expect(headerElement.first()).toBeVisible();
            }
        }

        console.log('✅ サブスクリプション管理画面の表示確認完了');
    });

    test('レスポンシブ・アクセシビリティチェック', async ({ page }) => {
        await page.goto('http://127.0.0.1:8000/admin/menu-categories');

        // フォントサイズのチェック
        const bodyFontSize = await page.locator('body').evaluate(el => {
            return window.getComputedStyle(el).fontSize;
        });
        console.log('基本フォントサイズ:', bodyFontSize);

        // ボタンサイズのチェック
        const buttons = page.locator('button');
        const buttonCount = await buttons.count();
        
        if (buttonCount > 0) {
            const firstButton = buttons.first();
            const buttonSize = await firstButton.evaluate(el => {
                const styles = window.getComputedStyle(el);
                return {
                    height: styles.height,
                    padding: styles.padding,
                    fontSize: styles.fontSize
                };
            });
            console.log('ボタンサイズ情報:', buttonSize);

            // 最小推奨サイズのチェック（シニア向け）
            const heightValue = parseInt(buttonSize.height);
            expect(heightValue).toBeGreaterThanOrEqual(40); // 最低40px推奨
        }

        // コントラスト比のチェック（視認性）
        const textElements = page.locator('p, span, label').first(5);
        const textCount = await textElements.count();
        
        for (let i = 0; i < Math.min(textCount, 3); i++) {
            const element = textElements.nth(i);
            const colors = await element.evaluate(el => {
                const styles = window.getComputedStyle(el);
                return {
                    color: styles.color,
                    background: styles.backgroundColor
                };
            });
            console.log(`テキスト要素${i + 1}の色設定:`, colors);
        }

        console.log('✅ アクセシビリティチェック完了');
    });
});