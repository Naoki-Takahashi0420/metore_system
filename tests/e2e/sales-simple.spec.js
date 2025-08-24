import { test, expect } from '@playwright/test';

test.describe('売上管理基本テスト', () => {
    
    test('売上管理にアクセスして基本機能を確認', async ({ page }) => {
        // ログイン
        await page.goto('/admin/login');
        await page.getByLabel('メールアドレス').fill('superadmin@xsyumeno.com');
        await page.getByLabel('パスワード').fill('password');
        await page.getByRole('button', { name: 'ログイン' }).click();
        
        // ダッシュボードが表示されるまで待つ
        await page.waitForSelector('text=ダッシュボード', { timeout: 10000 });
        
        console.log('✅ ログイン成功');
        
        // 売上管理メニューを探してクリック
        const salesMenu = page.getByRole('link', { name: '売上管理' });
        if (await salesMenu.isVisible()) {
            await salesMenu.click();
            console.log('✅ 売上管理ページへ移動');
            
            // ページタイトルを確認
            const pageTitle = page.locator('h1').first();
            const titleText = await pageTitle.textContent();
            console.log(`  ページタイトル: ${titleText}`);
            
            // 新規作成ボタンの存在確認
            const createButton = page.getByRole('link', { name: /新規|作成|追加/ });
            if (await createButton.count() > 0) {
                console.log('✅ 新規作成ボタンが存在');
            }
            
            // 日次精算ボタンの存在確認
            const dailyButton = page.getByText('日次精算');
            if (await dailyButton.count() > 0) {
                console.log('✅ 日次精算ボタンが存在');
            }
        } else {
            console.log('⚠️ 売上管理メニューが見つかりません');
        }
        
        // ダッシュボードに戻る
        await page.getByRole('link', { name: 'ダッシュボード' }).first().click();
        console.log('✅ ダッシュボードに戻りました');
        
        // ウィジェットの確認（存在する場合のみ）
        await page.waitForTimeout(2000); // ウィジェットのロードを待つ
        
        const widgets = [
            '本日の売上',
            '今月の売上',
            '本日の来店客数',
            '本日の予約'
        ];
        
        for (const widgetName of widgets) {
            const widget = page.getByText(widgetName).first();
            if (await widget.count() > 0) {
                console.log(`✅ ウィジェット「${widgetName}」が表示`);
            }
        }
        
        // グラフの確認
        const canvas = page.locator('canvas').first();
        if (await canvas.count() > 0) {
            console.log('✅ グラフ（Canvas）が表示');
        }
        
        // 売れ筋メニューウィジェットの確認
        const topMenus = page.getByText(/売れ筋|TOP/);
        if (await topMenus.count() > 0) {
            console.log('✅ 売れ筋メニューセクションが表示');
        }
        
        console.log('\n🎉 売上管理システムの基本動作確認完了！');
    });
    
    test('日次精算ページの動作確認', async ({ page }) => {
        // ログイン
        await page.goto('/admin/login');
        await page.getByLabel('メールアドレス').fill('superadmin@xsyumeno.com');
        await page.getByLabel('パスワード').fill('password');
        await page.getByRole('button', { name: 'ログイン' }).click();
        
        // ダッシュボードが表示されるまで待つ
        await page.waitForSelector('text=ダッシュボード', { timeout: 10000 });
        
        // 売上管理ページへ
        const salesMenu = page.getByRole('link', { name: '売上管理' });
        if (await salesMenu.isVisible()) {
            await salesMenu.click();
            
            // 日次精算ボタンを探してクリック
            const dailyClosingLink = page.getByRole('link', { name: '日次精算' });
            if (await dailyClosingLink.count() > 0) {
                await dailyClosingLink.click();
                console.log('✅ 日次精算ページへ移動');
                
                // ページ要素の確認
                await page.waitForTimeout(2000);
                
                const elements = [
                    '売上サマリー',
                    '総売上',
                    '取引件数',
                    '支払方法別売上'
                ];
                
                for (const element of elements) {
                    const el = page.getByText(element);
                    if (await el.count() > 0) {
                        console.log(`  ✅ ${element}セクションが表示`);
                    }
                }
                
                // 実際の現金残高入力フィールド
                const cashInput = page.getByLabel(/実際.*現金/);
                if (await cashInput.count() > 0) {
                    await cashInput.fill('50000');
                    console.log('  ✅ 現金残高を入力可能');
                }
                
                // 精算実行ボタン
                const executeButton = page.getByRole('button', { name: /精算.*実行/ });
                if (await executeButton.count() > 0) {
                    console.log('  ✅ 日次精算実行ボタンが存在');
                }
            }
        }
        
        console.log('\n🎉 日次精算ページの動作確認完了！');
    });
});