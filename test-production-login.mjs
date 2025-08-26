import { chromium } from 'playwright';

async function testProductionLogin() {
    const browser = await chromium.launch({ headless: false });
    const context = await browser.newContext();
    const page = await context.newPage();
    
    console.log('🌐 本番環境のログインページにアクセス...');
    await page.goto('http://54.64.54.226/admin/login');
    
    console.log('📝 ログイン情報を入力...');
    
    // メールアドレスを入力
    await page.fill('input[type="email"]', 'admin@eye-training.com');
    
    // パスワードを入力
    await page.fill('input[type="password"]', 'password');
    
    console.log('🚀 ログインボタンをクリック...');
    
    // ログインボタンをクリック
    await page.click('button[type="submit"]');
    
    // レスポンスを待つ
    await page.waitForTimeout(5000);
    
    // 現在のURL確認
    console.log('📍 現在のURL:', page.url());
    
    if (page.url().includes('/admin') && !page.url().includes('/admin/login')) {
        console.log('🎉 本番環境ログイン成功！！！');
        
        // ダッシュボードのスクリーンショット
        await page.screenshot({ path: 'production-dashboard.png', fullPage: true });
        
        // 店舗ページも確認
        await page.goto('http://54.64.54.226/admin/stores');
        await page.waitForTimeout(2000);
        console.log('📍 店舗ページ:', page.url());
        await page.screenshot({ path: 'production-stores.png', fullPage: true });
        
    } else {
        console.log('❌ ログイン失敗...');
        await page.screenshot({ path: 'production-login-failed.png', fullPage: true });
    }
    
    await browser.close();
}

testProductionLogin().catch(console.error);