import { chromium } from '@playwright/test';

(async () => {
    const browser = await chromium.launch({ headless: false });
    const page = await browser.newPage();

    // 管理画面にログイン
    await page.goto('http://localhost:8000/admin/login');
    await page.fill('input[name="email"]', 'admin@eye-training.com');
    await page.fill('input[name="password"]', 'password');
    await page.click('button[type="submit"]');

    // ダッシュボードに移動
    await page.waitForURL('**/admin');

    // タイムラインウィジェットが読み込まれるまで待つ
    await page.waitForSelector('.timeline-table', { timeout: 10000 });

    // 新規予約ボタンをクリック
    const newReservationBtn = await page.$('button:has-text("新規予約")');
    if (newReservationBtn) {
        await newReservationBtn.click();

        // モーダルが開くのを待つ
        await page.waitForSelector('.fixed.inset-0', { timeout: 5000 });

        // 電話番号入力（テスト用）
        await page.fill('input[wire\\:model="phoneSearch"]', '090');
        await page.waitForTimeout(1000);

        // 新規顧客登録ボタンをクリック
        const newCustomerBtn = await page.$('button:has-text("新規顧客として登録")');
        if (newCustomerBtn) {
            await newCustomerBtn.click();
            await page.waitForTimeout(500);

            // 顧客情報を入力
            await page.fill('input[wire\\:model="newCustomer.last_name"]', 'テスト');
            await page.fill('input[wire\\:model="newCustomer.first_name"]', '太郎');

            // 次へボタンをクリック
            const nextBtn = await page.$('button:has-text("次へ")');
            if (nextBtn) {
                await nextBtn.click();
                await page.waitForTimeout(1000);

                // メニューセレクトボックスが Tom Select に変換されているか確認
                const tomSelectContainer = await page.$('.ts-control');
                if (tomSelectContainer) {
                    console.log('✅ Tom Selectが正常に初期化されました');

                    // Tom Select をクリックして開く
                    await tomSelectContainer.click();
                    await page.waitForTimeout(500);

                    // 検索入力フィールドを探す
                    const searchInput = await page.$('.ts-control input');
                    if (searchInput) {
                        console.log('✅ 検索入力フィールドが見つかりました');

                        // 検索テスト
                        await searchInput.type('ベーシック');
                        await page.waitForTimeout(1000);

                        // スクリーンショットを撮る
                        await page.screenshot({ path: 'menu-search-result.png', fullPage: false });
                        console.log('✅ 検索機能のスクリーンショットを保存しました: menu-search-result.png');
                    } else {
                        console.log('❌ 検索入力フィールドが見つかりません');
                    }
                } else {
                    console.log('❌ Tom Selectが初期化されていません');

                    // 通常のセレクトボックスが存在するか確認
                    const normalSelect = await page.$('select[wire\\:model="newReservation.menu_id"]');
                    if (normalSelect) {
                        console.log('⚠️ 通常のセレクトボックスのままです');
                    }
                }

                // 全体のスクリーンショット
                await page.screenshot({ path: 'reservation-modal.png', fullPage: false });
                console.log('📸 モーダル全体のスクリーンショット: reservation-modal.png');
            }
        }
    } else {
        console.log('❌ 新規予約ボタンが見つかりません');
    }

    await page.waitForTimeout(3000);
    await browser.close();
})();