import { chromium } from 'playwright';

async function testLogin() {
    const browser = await chromium.launch({ headless: false });
    const context = await browser.newContext();
    const page = await context.newPage();
    
    console.log('🔍 ログインページにアクセス...');
    await page.goto('http://localhost:8000/admin/login');
    
    // ページのスクリーンショット
    await page.screenshot({ path: 'login-page.png', fullPage: true });
    
    console.log('📝 ログイン情報を入力...');
    
    // メールアドレスを入力
    await page.fill('input[type="email"]', 'admin@eye-training.com');
    
    // パスワードを入力
    await page.fill('input[type="password"]', 'password');
    
    // 入力後のスクリーンショット
    await page.screenshot({ path: 'login-filled.png', fullPage: true });
    
    console.log('🚀 ログインボタンをクリック...');
    
    // ネットワークログを監視
    page.on('request', request => {
        if (request.url().includes('login') || request.url().includes('livewire')) {
            console.log('>> Request:', request.method(), request.url());
        }
    });
    
    page.on('response', response => {
        if (response.url().includes('login') || response.url().includes('livewire')) {
            console.log('<< Response:', response.status(), response.url());
        }
    });
    
    // ログインボタンをクリック
    await page.click('button[type="submit"]');
    
    // レスポンスを待つ
    await page.waitForTimeout(3000);
    
    // 結果のスクリーンショット
    await page.screenshot({ path: 'login-result.png', fullPage: true });
    
    // 現在のURL確認
    console.log('📍 現在のURL:', page.url());
    
    // エラーメッセージを確認
    const errorElement = await page.$('.text-danger, .text-red-600, [role="alert"]');
    if (errorElement) {
        const errorText = await errorElement.textContent();
        console.log('❌ エラー:', errorText);
    }
    
    // ページのHTMLを確認（デバッグ用）
    const pageContent = await page.content();
    if (pageContent.includes('Method Not Allowed') || pageContent.includes('419')) {
        console.log('❌ CSRFまたはメソッドエラーが発生');
    }
    
    if (page.url().includes('dashboard') || page.url().includes('/admin') && !page.url().includes('/admin/login')) {
        console.log('✅ ログイン成功！ダッシュボードにリダイレクトされました');
    } else {
        console.log('❌ ログイン失敗');
    }
    
    await browser.close();
}

testLogin().catch(console.error);