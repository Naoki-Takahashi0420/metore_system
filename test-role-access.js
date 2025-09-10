import puppeteer from 'puppeteer';

async function testRoleAccess() {
    const browser = await puppeteer.launch({ 
        headless: false,
        defaultViewport: { width: 1400, height: 900 }
    });
    
    const testCases = [
        {
            email: 'admin@eye-training.com',
            password: 'password',
            role: 'super_admin',
            expectedBehavior: '全店舗のデータが見える'
        },
        {
            email: 'owner@test.com',
            password: 'password',
            role: 'owner',
            expectedBehavior: '銀座本店と新宿店のみ見える'
        },
        {
            email: 'manager1@eye-training.jp',
            password: 'password',
            role: 'manager（銀座本店）',
            expectedBehavior: '銀座本店のデータのみ見える'
        },
        {
            email: 'staff0_1@eye-training.jp',
            password: 'password',
            role: 'staff（銀座本店）',
            expectedBehavior: '銀座本店のデータのみ見える'
        },
        {
            email: 'manager2@eye-training.jp',
            password: 'password',
            role: 'manager（新宿店）',
            expectedBehavior: '新宿店のデータのみ見える'
        }
    ];
    
    for (const testCase of testCases) {
        console.log(`\n=== ${testCase.role} のテスト ===`);
        console.log(`期待: ${testCase.expectedBehavior}`);
        
        const page = await browser.newPage();
        
        try {
            // ログイン
            await page.goto('http://localhost:8002/admin/login');
            await page.waitForSelector('input[type="email"]');
            await page.type('input[type="email"]', testCase.email);
            await page.type('input[type="password"]', testCase.password);
            await page.click('button[type="submit"]');
            
            // ダッシュボードが表示されるまで待機
            await page.waitForNavigation();
            await new Promise(resolve => setTimeout(resolve, 2000));
            
            // 予約管理ページへ移動
            await page.goto('http://localhost:8002/admin/reservations');
            await page.waitForSelector('table', { timeout: 5000 });
            
            // 表示されている店舗名を取得
            const storeNames = await page.evaluate(() => {
                const storeCells = document.querySelectorAll('td[class*="text-column"]');
                const stores = new Set();
                storeCells.forEach(cell => {
                    const text = cell.textContent.trim();
                    if (text && !text.includes(':') && !text.includes('¥')) {
                        // 店舗名っぽいものを抽出
                        if (text.includes('本店') || text.includes('店')) {
                            stores.add(text);
                        }
                    }
                });
                return Array.from(stores);
            });
            
            console.log(`表示された店舗: ${storeNames.length > 0 ? storeNames.join(', ') : 'なし'}`);
            
            // 顧客管理ページへ移動
            await page.goto('http://localhost:8002/admin/customers');
            await new Promise(resolve => setTimeout(resolve, 2000));
            
            // 顧客数を取得
            const customerCount = await page.evaluate(() => {
                const rows = document.querySelectorAll('tbody tr');
                return rows.length;
            });
            
            console.log(`表示された顧客数: ${customerCount}`);
            
            // ログアウト
            await page.evaluate(() => {
                const logoutForm = document.querySelector('form[action*="logout"]');
                if (logoutForm) {
                    logoutForm.submit();
                }
            });
            
            console.log(`✅ ${testCase.role} のテスト完了`);
            
        } catch (error) {
            console.error(`❌ エラー: ${error.message}`);
        } finally {
            await page.close();
        }
    }
    
    await browser.close();
    console.log('\n=== 全テスト完了 ===');
}

testRoleAccess().catch(console.error);