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

        // タイムラインウィジェットが読み込まれるまで待つ
        await page.waitForSelector('.timeline-table', { timeout: 15000 });
        console.log('✅ タイムラインウィジェットが読み込まれました');

        // 新規予約ボタンをクリック
        await page.click('button:has-text("新規予約")');
        console.log('✅ 新規予約ボタンをクリックしました');

        // モーダルが開くのを待つ
        await page.waitForSelector('.fixed.inset-0', { timeout: 10000 });
        console.log('✅ モーダルが開きました');

        // 電話番号を入力
        const phoneInput = await page.waitForSelector('input[placeholder*="電話番号"]', { timeout: 5000 });
        await phoneInput.fill('090-1234-5678');
        await page.waitForTimeout(1500);

        // 新規顧客として登録ボタンをクリック
        await page.click('button:has-text("新規顧客として登録")');
        console.log('✅ 新規顧客として登録をクリック');
        await page.waitForTimeout(1000);

        // 顧客情報を入力
        await page.fill('input[placeholder*="姓"]', 'テスト');
        await page.fill('input[placeholder*="名"]', '太郎');
        console.log('✅ 顧客情報を入力しました');

        // 次へボタンをクリック
        await page.click('button:has-text("次へ")');
        console.log('✅ 次へボタンをクリック');
        await page.waitForTimeout(2000);

        // メニュー選択セクションを探す
        const menuLabel = await page.waitForSelector('label:has-text("メニュー")', { timeout: 5000 });
        console.log('✅ メニューセクションを発見');

        // Tom Select のコンテナを探す
        const tomSelectContainer = await page.$('.ts-control');

        if (tomSelectContainer) {
            console.log('✅✅✅ Tom Selectが正常に初期化されました！');

            // コンテナをクリックしてドロップダウンを開く
            await tomSelectContainer.click();
            await page.waitForTimeout(500);

            // 検索入力フィールドを探す
            const searchInput = await page.$('.ts-control input[type="text"]');

            if (searchInput) {
                console.log('✅✅✅ 検索入力フィールドを発見！');

                // 検索文字を入力
                await searchInput.type('ベーシック');
                await page.waitForTimeout(1500);

                // 検索結果のスクリーンショット
                await page.screenshot({
                    path: 'menu-search-success.png',
                    clip: { x: 300, y: 200, width: 600, height: 400 }
                });
                console.log('✅✅✅ 検索機能が正常に動作しています！');
                console.log('📸 スクリーンショット: menu-search-success.png');
            } else {
                console.log('⚠️ 検索入力フィールドが見つかりません');

                // デバッグのため、利用可能な入力フィールドを確認
                const allInputs = await page.$$('.ts-control input');
                console.log(`見つかった入力フィールド数: ${allInputs.length}`);
            }
        } else {
            console.log('❌ Tom Selectが初期化されていません');

            // 通常のセレクトボックスを確認
            const normalSelect = await page.$('select[wire\\:model="newReservation.menu_id"]');
            if (normalSelect) {
                console.log('⚠️ 通常のセレクトボックスのままです');

                // セレクトボックスのオプション数を確認
                const options = await normalSelect.$$eval('option', opts => opts.length);
                console.log(`メニューオプション数: ${options}`);

                // デバッグ用スクリーンショット
                await page.screenshot({
                    path: 'menu-select-normal.png',
                    clip: { x: 300, y: 200, width: 600, height: 400 }
                });
                console.log('📸 通常のセレクトボックスのスクリーンショット: menu-select-normal.png');
            } else {
                console.log('❌ セレクトボックスも見つかりません');
            }
        }

        // モーダル全体のスクリーンショット
        await page.screenshot({ path: 'reservation-modal-full.png', fullPage: false });
        console.log('📸 モーダル全体のスクリーンショット: reservation-modal-full.png');

    } catch (error) {
        console.error('❌ エラーが発生しました:', error.message);

        // エラー時のスクリーンショット
        await page.screenshot({ path: 'error-screenshot.png', fullPage: true });
        console.log('📸 エラー時のスクリーンショット: error-screenshot.png');
    }

    await page.waitForTimeout(5000);
    await browser.close();
})();