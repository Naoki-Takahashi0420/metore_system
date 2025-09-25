import { chromium } from 'playwright';

async function testReservation() {
    const browser = await chromium.launch({
        headless: false,
        slowMo: 500
    });

    const context = await browser.newContext();
    const page = await context.newPage();

    try {
        console.log('1. ログインページへアクセス...');
        await page.goto('http://localhost:8000/admin/login');

        console.log('2. ログイン中...');
        await page.fill('input[name="email"]', 'admin@eye-training.com');
        await page.fill('input[name="password"]', 'password');
        await page.click('button[type="submit"]');

        await page.waitForURL('**/admin');
        console.log('✅ ログイン成功');

        console.log('3. 予約管理へ移動...');
        await page.goto('http://localhost:8000/admin/reservation-timeline-widgets');
        await page.waitForSelector('.reservation-timeline', { timeout: 10000 });

        console.log('4. タイムラインクリック...');
        // 13:00の時間枠をクリック
        const timeSlot = await page.locator('.time-slot[data-time="13:00"]').first();
        await timeSlot.click();

        // モーダルが表示されるのを待つ
        await page.waitForSelector('.modal-container', { timeout: 5000 });
        console.log('✅ モーダル表示確認');

        // 顧客を選択
        console.log('5. 顧客選択...');
        await page.click('button:has-text("顧客を選択")');
        await page.waitForTimeout(1000);

        // 最初の顧客を選択
        const customerItem = await page.locator('.customer-list-item').first();
        if (await customerItem.count() > 0) {
            await customerItem.click();
            console.log('✅ 顧客選択完了');
        }

        // メニューを選択
        console.log('6. メニュー選択...');
        await page.click('button:has-text("メニューを選択")');
        await page.waitForTimeout(1000);

        const menuItem = await page.locator('.menu-list-item').first();
        if (await menuItem.count() > 0) {
            await menuItem.click();
            console.log('✅ メニュー選択完了');
        }

        // 予約作成
        console.log('7. 予約作成中...');
        await page.click('button:has-text("予約を作成")');

        // 成功通知を待つ
        const notification = await page.waitForSelector('.notification-success', { timeout: 5000 });
        const message = await notification.textContent();
        console.log(`✅ 予約作成成功: ${message}`);

        // タイムラインで新しい予約を確認
        await page.waitForTimeout(2000);
        const newReservation = await page.locator('.reservation-block').last();
        if (await newReservation.count() > 0) {
            const reservationText = await newReservation.textContent();
            console.log(`✅ タイムラインに予約表示: ${reservationText}`);
        }

    } catch (error) {
        console.error('❌ エラー:', error.message);

        // スクリーンショット保存
        await page.screenshot({ path: 'error-screenshot.png' });
        console.log('スクリーンショット保存: error-screenshot.png');
    }

    console.log('\nテスト完了。ブラウザは開いたままです。');
    console.log('Ctrl+C で終了してください。');

    // ブラウザを開いたままにする
    await new Promise(() => {});
}

testReservation().catch(console.error);