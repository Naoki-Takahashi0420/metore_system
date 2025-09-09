import { chromium } from 'playwright';

(async () => {
    const browser = await chromium.launch({ 
        headless: false,
        devtools: false
    });
    
    try {
        // iPhone SEサイズでテスト
        const context = await browser.newContext({
            viewport: { width: 375, height: 667 },
            userAgent: 'Mozilla/5.0 (iPhone; CPU iPhone OS 14_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/14.0 Mobile/15E148 Safari/604.1'
        });
        
        const page = await context.newPage();
        
        // ログイン
        console.log('1. ログインページへ移動');
        await page.goto('http://localhost:8000/customer/login');
        await page.waitForSelector('#phone');
        
        console.log('2. 電話番号を入力');
        await page.fill('#phone', '08033372305');
        await page.click('button[type="submit"]');
        
        // OTP入力 - モーダルが表示されるまで待つ
        await page.waitForTimeout(1000);
        await page.waitForSelector('#otpModal', { state: 'visible', timeout: 10000 });
        console.log('3. OTPを入力');
        const otpInputs = await page.locator('#otpModal input[type="text"]').all();
        for (let i = 0; i < otpInputs.length; i++) {
            await otpInputs[i].fill('0');
        }
        
        // ダッシュボードで予約を確認
        await page.waitForURL('**/customer/dashboard');
        console.log('4. ダッシュボードで予約を確認');
        
        // 次回予約が表示されるまで待つ
        await page.waitForSelector('.reservation-card', { timeout: 10000 });
        
        // 日程変更ボタンをクリック
        console.log('5. 日程変更ボタンをクリック');
        const changeButton = await page.locator('button:has-text("日程変更")').first();
        await changeButton.click();
        
        // カレンダーページへ遷移
        await page.waitForURL('**/reservation/calendar');
        console.log('6. カレンダーページに遷移');
        
        // 黄色のハイライト表示を確認
        const yellowHighlight = await page.locator('.bg-yellow-500').first();
        const isVisible = await yellowHighlight.isVisible();
        console.log('7. 黄色のハイライト表示:', isVisible ? '表示されている' : '表示されていない');
        
        // 現在バッジを確認
        const currentBadge = await page.locator('span:has-text("現在")').first();
        const badgeVisible = await currentBadge.isVisible();
        console.log('8. 現在バッジ:', badgeVisible ? '表示されている' : '表示されていない');
        
        // 情報バナーを確認
        const infoBanner = await page.locator('.bg-yellow-50').first();
        const bannerVisible = await infoBanner.isVisible();
        console.log('9. 情報バナー:', bannerVisible ? '表示されている' : '表示されていない');
        
        // モバイルでのレイアウトを確認
        await page.screenshot({ path: 'mobile-date-change.png', fullPage: true });
        console.log('10. スクリーンショット保存: mobile-date-change.png');
        
        // 少し待ってから終了
        await page.waitForTimeout(3000);
        
    } catch (error) {
        console.error('エラー:', error);
    } finally {
        await browser.close();
    }
})();