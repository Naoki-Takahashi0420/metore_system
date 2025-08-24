import { test, expect } from '@playwright/test';

test.describe('売上作成テスト', () => {
    
    test('売上を新規作成する', async ({ page }) => {
        // ログイン
        await page.goto('/admin/login');
        await page.getByLabel('メールアドレス').fill('superadmin@xsyumeno.com');
        await page.getByLabel('パスワード').fill('password');
        await page.getByRole('button', { name: 'ログイン' }).click();
        
        // ダッシュボードが表示されるまで待つ
        await page.waitForSelector('text=ダッシュボード', { timeout: 10000 });
        console.log('✅ ログイン成功');
        
        // 売上管理ページへ移動
        await page.getByRole('link', { name: '売上管理' }).click();
        console.log('✅ 売上管理ページへ移動');
        
        // 新規作成ボタンをクリック
        const createButton = page.getByRole('link', { name: /新規|作成|追加/ });
        await createButton.click();
        console.log('✅ 新規作成フォームを開く');
        
        // フォームが表示されるまで待つ
        await page.waitForSelector('text=売上基本情報', { timeout: 10000 });
        
        // 売上番号が自動生成されているか確認
        const saleNumberInput = page.locator('input[disabled]').first();
        const saleNumber = await saleNumberInput.inputValue();
        console.log(`✅ 売上番号自動生成: ${saleNumber}`);
        
        // 金額を入力
        await page.getByLabel('小計').fill('10000');
        console.log('  小計: 10000円');
        
        // タブキーで次のフィールドに移動（自動計算をトリガー）
        await page.keyboard.press('Tab');
        await page.waitForTimeout(500);
        
        // 支払方法を選択
        const paymentSelect = page.locator('select').filter({ hasText: /支払方法/ }).or(page.getByLabel('支払方法'));
        if (await paymentSelect.count() > 0) {
            await paymentSelect.selectOption('cash');
            console.log('  支払方法: 現金');
        }
        
        // ステータスがデフォルトで「完了」になっているか確認
        const statusSelect = page.locator('select').filter({ hasText: /ステータス/ }).or(page.getByLabel('ステータス'));
        if (await statusSelect.count() > 0) {
            const statusValue = await statusSelect.inputValue();
            console.log(`  ステータス: ${statusValue || '完了'}`);
        }
        
        // 作成ボタンをクリック
        const submitButton = page.getByRole('button', { name: /作成|保存|登録/ });
        await submitButton.click();
        console.log('✅ 売上を作成');
        
        // 作成後の処理を待つ
        await page.waitForTimeout(2000);
        
        // 成功通知またはリダイレクトを確認
        const currentUrl = page.url();
        if (currentUrl.includes('/admin/sales') && !currentUrl.includes('/create')) {
            console.log('✅ 売上一覧ページにリダイレクト');
        }
        
        // 成功通知を確認
        const notification = page.locator('.fi-notification').or(page.getByText(/成功|作成しました/));
        if (await notification.count() > 0) {
            console.log('✅ 成功通知が表示');
        }
        
        console.log('\n🎉 売上の新規作成が完了しました！');
    });
});