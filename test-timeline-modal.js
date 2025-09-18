import { chromium } from '@playwright/test';

(async () => {
    const browser = await chromium.launch({ headless: false });
    const page = await browser.newPage();

    try {
        // 管理画面にアクセス
        await page.goto('http://localhost:8000/admin/login');

        // ログイン
        await page.waitForSelector('input[type="email"]', { timeout: 10000 });
        await page.fill('input[type="email"]', 'admin@eye-training.com');
        await page.fill('input[type="password"]', 'password');
        await page.click('button[type="submit"]');

        // ダッシュボードに移動
        await page.waitForURL('**/admin', { timeout: 10000 });
        console.log('✅ ダッシュボードにログイン');

        // タイムラインウィジェットを待つ
        await page.waitForSelector('.timeline-table', { timeout: 15000 });
        console.log('✅ タイムラインウィジェットが表示');

        // タイムラインの空きスロットをクリック
        const emptySlot = await page.$('.timeline-slot.empty');
        if (emptySlot) {
            await emptySlot.click();
            console.log('✅ 空きスロットをクリック');
        } else {
            console.log('⚠️ 空きスロットが見つからない');
        }

        // モーダルが開くのを待つ
        await page.waitForTimeout(2000);

        // モーダル内を確認
        const modal = await page.$('.fixed.inset-0');
        if (modal) {
            console.log('✅ モーダルが表示されました');

            // 電話番号入力
            const phoneInput = await page.$('input[placeholder*="電話番号"]');
            if (phoneInput) {
                await phoneInput.fill('090-1234-5678');
                await page.waitForTimeout(1000);
                console.log('✅ 電話番号を入力');

                // 新規顧客として登録
                await page.click('button:has-text("新規顧客")');
                await page.waitForTimeout(1000);

                // 顧客情報入力
                await page.fill('input[placeholder*="姓"]', 'テスト');
                await page.fill('input[placeholder*="名"]', '太郎');

                // 次へボタン
                await page.click('button:has-text("次へ")');
                await page.waitForTimeout(2000);
                console.log('✅ ステップ3に進みました');

                // コンソールログを確認
                page.on('console', msg => console.log('Browser console:', msg.text()));

                // Tom Selectが初期化されたか確認
                const tomSelectWrapper = await page.$('.ts-wrapper');
                const tomSelectControl = await page.$('.ts-control');

                if (tomSelectWrapper || tomSelectControl) {
                    console.log('✅✅✅ Tom Selectが初期化されています！');
                } else {
                    console.log('❌ Tom Selectが初期化されていません');

                    // 手動初期化を試す
                    await page.evaluate(() => {
                        if (window.initMenuSelect) {
                            window.initMenuSelect();
                            console.log('Manual initialization triggered');
                        }
                    });

                    await page.waitForTimeout(1000);

                    // 再度確認
                    const tomSelectAfter = await page.$('.ts-wrapper');
                    if (tomSelectAfter) {
                        console.log('✅ 手動初期化成功！');
                    }
                }

                // スクリーンショット
                await page.screenshot({ path: 'timeline-modal-menu.png', fullPage: false });
                console.log('📸 スクリーンショット: timeline-modal-menu.png');
            }
        } else {
            console.log('❌ モーダルが開きませんでした');
        }

    } catch (error) {
        console.error('❌ エラー:', error.message);
        await page.screenshot({ path: 'error.png', fullPage: true });
    }

    await page.waitForTimeout(5000);
    await browser.close();
})();