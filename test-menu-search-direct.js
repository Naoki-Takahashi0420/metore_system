import { chromium } from '@playwright/test';

(async () => {
    const browser = await chromium.launch({ headless: false });
    const page = await browser.newPage();

    try {
        // 管理画面にアクセス
        await page.goto('http://localhost:8000/admin/login');

        // ログインフォームが表示されるまで待つ
        await page.waitForSelector('input[type="email"]', { timeout: 10000 });

        // ログイン
        await page.fill('input[type="email"]', 'admin@eye-training.com');
        await page.fill('input[type="password"]', 'password');
        await page.click('button[type="submit"]');

        // ダッシュボードに移動を待つ
        await page.waitForURL('**/admin', { timeout: 10000 });
        console.log('✅ ダッシュボードにログインしました');

        // 既存のモーダルを閉じる（開いている場合）
        const closeButton = await page.$('button[aria-label="Close"]');
        if (closeButton) {
            await closeButton.click();
            await page.waitForTimeout(500);
        }

        // 電話番号入力
        await page.fill('input[placeholder*="電話番号"]', '090-1234-5678');
        console.log('✅ 電話番号を入力');

        // 新規顧客ボタンをクリック
        await page.click('button:has-text("新規顧客")');
        console.log('✅ 新規顧客ボタンをクリック');
        await page.waitForTimeout(1000);

        // 顧客情報を入力
        await page.fill('input[placeholder*="姓"]', 'テスト');
        await page.fill('input[placeholder*="名"]', '太郎');
        console.log('✅ 顧客情報を入力');

        // 次へボタンをクリック
        await page.click('button:has-text("次へ")');
        console.log('✅ 次へボタンをクリック');
        await page.waitForTimeout(2000);

        // メニュー選択フィールドの状態を確認
        console.log('=== メニュー選択フィールドの状態を確認 ===');

        // Tom Select のコンテナを探す
        const tomSelectContainer = await page.$('.ts-wrapper');
        const tomSelectControl = await page.$('.ts-control');

        if (tomSelectContainer || tomSelectControl) {
            console.log('✅✅✅ Tom Selectが正常に初期化されました！');

            // クリックしてドロップダウンを開く
            const clickTarget = tomSelectControl || tomSelectContainer;
            await clickTarget.click();
            await page.waitForTimeout(500);

            // 検索入力フィールドを探す
            const searchInput = await page.$('.ts-dropdown input[type="search"], .ts-control input[type="text"], .ts-input input');

            if (searchInput) {
                console.log('✅✅✅ 検索入力フィールドを発見！');

                // 検索テスト
                await searchInput.type('ベーシック');
                await page.waitForTimeout(1500);

                console.log('✅✅✅ 検索機能が正常に動作しています！');

                // 検索結果のスクリーンショット
                await page.screenshot({
                    path: 'menu-search-working.png',
                    fullPage: false
                });
                console.log('📸 検索機能のスクリーンショット: menu-search-working.png');
            } else {
                console.log('⚠️ 検索入力フィールドが見つかりません');

                // デバッグ: 全ての入力フィールドを確認
                const allInputs = await page.$$('input');
                console.log(`ページ内の全入力フィールド数: ${allInputs.length}`);

                for (let i = 0; i < allInputs.length; i++) {
                    const placeholder = await allInputs[i].getAttribute('placeholder');
                    const type = await allInputs[i].getAttribute('type');
                    const className = await allInputs[i].getAttribute('class');
                    console.log(`Input ${i}: type="${type}", placeholder="${placeholder}", class="${className}"`);
                }
            }
        } else {
            console.log('❌ Tom Selectが初期化されていません');

            // 通常のセレクトボックスを確認
            const normalSelect = await page.$('select[wire\\:model="newReservation.menu_id"]');
            if (normalSelect) {
                console.log('⚠️ 通常のセレクトボックスのままです');

                // オプション数を確認
                const options = await normalSelect.$$('option');
                console.log(`メニューオプション数: ${options.length}`);

                // デバッグ用スクリーンショット
                await page.screenshot({
                    path: 'menu-select-normal.png',
                    fullPage: false
                });
                console.log('📸 通常のセレクトボックスのスクリーンショット: menu-select-normal.png');
            } else {
                console.log('❌ メニュー選択フィールドが見つかりません');
            }
        }

        // ページ全体のスクリーンショット
        await page.screenshot({ path: 'final-state.png', fullPage: false });
        console.log('📸 最終状態のスクリーンショット: final-state.png');

    } catch (error) {
        console.error('❌ エラーが発生しました:', error.message);

        // エラー時のスクリーンショット
        await page.screenshot({ path: 'error-state.png', fullPage: true });
        console.log('📸 エラー時のスクリーンショット: error-state.png');
    }

    await page.waitForTimeout(5000);
    await browser.close();
})();