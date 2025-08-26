import { chromium } from 'playwright';

async function testNaokiLogin() {
    const browser = await chromium.launch({ headless: false });
    const context = await browser.newContext();
    const page = await context.newPage();
    
    console.log('🌐 Naokiさんのアカウントでログインテスト...');
    await page.goto('http://54.64.54.226/admin/login');
    
    // メールアドレスを入力
    await page.fill('input[type="email"]', 'naoki@yumeno-marketing.jp');
    
    // パスワードを入力
    await page.fill('input[type="password"]', 'Takahashi5000');
    
    // ログインボタンをクリック
    await page.click('button[type="submit"]');
    
    // レスポンスを待つ
    await page.waitForTimeout(3000);
    
    // 現在のURL確認
    console.log('📍 現在のURL:', page.url());
    
    if (page.url().includes('/admin') && !page.url().includes('/admin/login')) {
        console.log('✅ Naokiさんのアカウントでログイン成功！');
        await page.screenshot({ path: 'naoki-dashboard.png', fullPage: true });
    } else {
        console.log('❌ ログイン失敗');
    }
    
    await browser.close();
}

testNaokiLogin().catch(console.error);