import { chromium } from 'playwright';

(async () => {
    const browser = await chromium.launch({ 
        headless: false,
        slowMo: 500 
    });
    const page = await browser.newPage();
    
    // コンソールログを出力
    page.on('console', msg => {
        console.log('BROWSER LOG:', msg.text());
    });
    
    // エラーを出力
    page.on('pageerror', error => {
        console.log('BROWSER ERROR:', error.message);
    });
    
    try {
        console.log('1. ログインページへ移動');
        await page.goto('http://localhost:8000/customer/login');
        await page.waitForTimeout(2000);
        
        // 電話番号入力
        console.log('2. 電話番号を入力');
        await page.fill('#phone', '08033372305');
        await page.click('#send-otp-button');
        await page.waitForTimeout(2000);
        
        // OTP入力
        console.log('3. OTPを入力');
        await page.fill('#otp1', '1');
        await page.fill('#otp2', '2');
        await page.fill('#otp3', '3');
        await page.fill('#otp4', '4');
        await page.fill('#otp5', '5');
        await page.fill('#otp6', '6');
        
        await page.click('#verify-otp');
        await page.waitForTimeout(3000);
        
        // ダッシュボードが表示されるまで待つ
        console.log('4. ダッシュボードを確認');
        await page.waitForSelector('#customer-info', { timeout: 10000 });
        
        // 予約データのロードを待つ
        await page.waitForTimeout(5000);
        
        // 次回予約セクションを確認
        const nextReservationVisible = await page.isVisible('#next-reservation');
        console.log('次回予約セクション表示:', nextReservationVisible);
        
        if (nextReservationVisible) {
            const nextReservationText = await page.textContent('#next-reservation-details');
            console.log('次回予約内容:', nextReservationText);
        }
        
        // サブスクセクションを確認
        const subscriptionVisible = await page.isVisible('#subscription-section');
        console.log('サブスクセクション表示:', subscriptionVisible);
        
        if (subscriptionVisible) {
            const subscriptionText = await page.textContent('#subscription-details');
            console.log('サブスク内容:', subscriptionText);
        }
        
        // 予約一覧を確認
        const reservationsVisible = await page.isVisible('#reservations-container');
        console.log('予約一覧表示:', reservationsVisible);
        
        // 空の状態を確認
        const emptyStateVisible = await page.isVisible('#empty-state');
        console.log('空の状態表示:', emptyStateVisible);
        
        // スクリーンショットを撮る
        await page.screenshot({ path: 'dashboard-screenshot.png', fullPage: true });
        console.log('スクリーンショット保存: dashboard-screenshot.png');
        
        // ブラウザは開いたままにする
        console.log('\n=== ブラウザを開いたままにしています ===');
        console.log('確認が終わったらCtrl+Cで終了してください');
        
        // 無限ループで待機
        await new Promise(() => {});
        
    } catch (error) {
        console.error('エラー:', error);
        await browser.close();
    }
})();