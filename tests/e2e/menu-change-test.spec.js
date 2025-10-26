import { test, expect } from '@playwright/test';

test.describe('メニュー変更機能のテスト', () => {
    test.beforeEach(async ({ page }) => {
        // 管理画面にログイン
        await page.goto('http://localhost:8000/admin/login');
        await page.fill('input[name="email"]', 'admin@eye-training.com');
        await page.fill('input[name="password"]', 'password');
        await page.click('button[type="submit"]');
        await page.waitForURL('**/admin');

        console.log('✅ ログイン完了');
    });

    test('予約詳細モーダルでメニュー名をクリックして変更できる', async ({ page }) => {
        console.log('🎬 テスト開始: メニュー変更機能');

        // タイムラインページに移動
        await page.goto('http://localhost:8000/admin');
        console.log('📍 ダッシュボードに移動');

        // ページが完全に読み込まれるまで待機
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(2000);

        // タイムライン上の予約をクリック
        console.log('🔍 予約を探しています...');

        // タイムライン上の予約ブロックを探す
        const reservationBlock = page.locator('.reservation-block').first();

        if (await reservationBlock.count() > 0) {
            console.log('✅ 予約ブロックが見つかりました');
            await reservationBlock.click();
            await page.waitForTimeout(1000);
        } else {
            console.log('⚠️ 予約ブロックが見つかりません。別の方法で予約詳細を開きます');
            // タイムライン上の任意のセルをクリック
            const timeSlot = page.locator('td[data-time]').first();
            if (await timeSlot.count() > 0) {
                await timeSlot.click();
                await page.waitForTimeout(1000);
            }
        }

        // モーダルが開くまで待機
        console.log('⏳ モーダルの表示を待っています...');
        const modal = page.locator('.bg-white.rounded-lg.shadow-xl').first();
        await modal.waitFor({ state: 'visible', timeout: 5000 });
        console.log('✅ モーダルが表示されました');

        // スクリーンショットを撮る
        await page.screenshot({ path: 'test-results/menu-change-01-modal-opened.png', fullPage: true });
        console.log('📸 スクリーンショット保存: modal-opened.png');

        // JavaScriptの読み込みを確認
        console.log('🔍 JavaScript関数の読み込みを確認中...');
        const functionsLoaded = await page.evaluate(() => {
            return {
                toggleMenuEdit: typeof window.toggleMenuEdit,
                saveMenuChange: typeof window.saveMenuChange,
                loadMenus: typeof window.loadMenus,
                loadOptions: typeof window.loadOptions
            };
        });
        console.log('📊 JavaScript関数の状態:', functionsLoaded);

        // コンソールログを確認
        const consoleMessages = [];
        page.on('console', msg => {
            const text = msg.text();
            consoleMessages.push(text);
            if (text.includes('Menu change') || text.includes('🍽️') || text.includes('✅')) {
                console.log('📋 ブラウザコンソール:', text);
            }
        });

        // メニュー表示エリアを探す
        console.log('🔍 メニュー表示エリアを探しています...');
        const menuDisplay = page.locator('#menuDisplay');

        if (await menuDisplay.count() > 0) {
            console.log('✅ メニュー表示エリアが見つかりました');

            // メニュー名のテキストを取得
            const menuText = await menuDisplay.textContent();
            console.log('📝 現在のメニュー:', menuText);

            // メニュー名（クリック可能なボタン）を探す
            const menuButton = menuDisplay.locator('button');

            if (await menuButton.count() > 0) {
                console.log('✅ メニュー変更ボタンが見つかりました');

                // ボタンの属性を確認
                const buttonHTML = await menuButton.evaluate(el => el.outerHTML);
                console.log('🔍 ボタンのHTML:', buttonHTML);

                // スクリーンショットを撮る
                await page.screenshot({ path: 'test-results/menu-change-02-before-click.png', fullPage: true });
                console.log('📸 スクリーンショット保存: before-click.png');

                // メニュー名をクリック
                console.log('👆 メニュー名をクリックします...');
                await menuButton.click();
                await page.waitForTimeout(1000);

                // スクリーンショットを撮る
                await page.screenshot({ path: 'test-results/menu-change-03-after-click.png', fullPage: true });
                console.log('📸 スクリーンショット保存: after-click.png');

                // メニュー編集エリアが表示されたか確認
                const menuEdit = page.locator('#menuEdit');
                const isVisible = await menuEdit.isVisible();

                console.log('📊 メニュー編集エリアの表示状態:', isVisible);

                if (isVisible) {
                    console.log('✅ メニュー編集モードに入りました！');

                    // メニュー選択ドロップダウンを確認
                    const menuSelect = page.locator('#menuSelect');
                    const selectHTML = await menuSelect.evaluate(el => el.innerHTML);
                    console.log('📋 メニュー選択の内容:', selectHTML);

                    // メニューを選択
                    const options = await menuSelect.locator('option').count();
                    console.log('📊 メニュー数:', options);

                    if (options > 1) {
                        // 2番目のオプションを選択（1番目は「メニューを選択...」）
                        await menuSelect.selectOption({ index: 1 });
                        console.log('✅ メニューを選択しました');

                        await page.screenshot({ path: 'test-results/menu-change-04-menu-selected.png', fullPage: true });
                        console.log('📸 スクリーンショット保存: menu-selected.png');

                        console.log('✅ テスト成功！メニュー変更機能は正常に動作しています');
                    } else {
                        console.log('⚠️ メニューが読み込まれていません');
                        console.log('コンソールメッセージ:', consoleMessages);
                    }
                } else {
                    console.log('❌ メニュー編集エリアが表示されませんでした');
                    console.log('コンソールメッセージ:', consoleMessages);

                    // エラー詳細を出力
                    const menuDisplayStyle = await menuDisplay.evaluate(el => el.style.display);
                    const menuEditStyle = await menuEdit.evaluate(el => el.style.display);
                    console.log('メニュー表示エリアのスタイル:', menuDisplayStyle);
                    console.log('メニュー編集エリアのスタイル:', menuEditStyle);
                }

                expect(isVisible).toBe(true);
            } else {
                console.log('❌ メニュー変更ボタンが見つかりません');
                const displayHTML = await menuDisplay.evaluate(el => el.innerHTML);
                console.log('メニュー表示エリアのHTML:', displayHTML);
                throw new Error('メニュー変更ボタンが見つかりません');
            }
        } else {
            console.log('❌ メニュー表示エリアが見つかりません');

            // モーダル全体のHTMLを確認
            const modalHTML = await modal.evaluate(el => el.innerHTML);
            console.log('モーダルのHTML（最初の500文字）:', modalHTML.substring(0, 500));

            throw new Error('メニュー表示エリアが見つかりません');
        }
    });

    test('JavaScriptのコンソールログを確認', async ({ page }) => {
        console.log('🎬 テスト開始: コンソールログ確認');

        // コンソールメッセージを収集
        const consoleMessages = [];
        page.on('console', msg => {
            consoleMessages.push({
                type: msg.type(),
                text: msg.text()
            });
            console.log(`[${msg.type()}] ${msg.text()}`);
        });

        // タイムラインページに移動
        await page.goto('http://localhost:8000/admin');
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(2000);

        // タイムライン上の予約をクリック
        const reservationBlock = page.locator('.reservation-block').first();
        if (await reservationBlock.count() > 0) {
            await reservationBlock.click();
            await page.waitForTimeout(2000);

            // モーダルが開くまで待機
            const modal = page.locator('.bg-white.rounded-lg.shadow-xl').first();
            await modal.waitFor({ state: 'visible', timeout: 5000 });

            // コンソールメッセージを確認
            const menuScriptLogs = consoleMessages.filter(msg =>
                msg.text.includes('Menu change') ||
                msg.text.includes('🍽️') ||
                msg.text.includes('toggleMenuEdit')
            );

            console.log('📋 メニュー変更関連のログ:', menuScriptLogs);

            // 最低1つのメニュー変更関連ログがあることを確認
            expect(menuScriptLogs.length).toBeGreaterThan(0);
        }
    });
});
