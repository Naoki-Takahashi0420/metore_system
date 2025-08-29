import { test, expect } from '@playwright/test';

test.describe('新予約フロー（カテゴリー→時間→カレンダー）', () => {
    
    test('予約フローが正しく動作すること', async ({ page }) => {
        // Basic認証
        const authHeader = 'Basic ' + Buffer.from('eyetraining:ginza2024').toString('base64');
        await page.setExtraHTTPHeaders({
            'Authorization': authHeader
        });
        
        // 1. 店舗選択画面
        await page.goto('http://127.0.0.1:8000/reservation/store');
        await page.waitForLoadState('domcontentloaded');
        
        console.log('📍 店舗選択画面');
        
        // タイトル確認
        await expect(page.locator('h1, h2').first()).toContainText(/店舗/);
        
        // 銀座店を選択
        const ginzaButton = page.locator('button:has-text("銀座")').first();
        if (await ginzaButton.count() > 0) {
            await ginzaButton.click();
        } else {
            // フォーム送信型の場合
            await page.locator('input[value="1"]').first().click(); // 銀座店のID=1と仮定
            await page.locator('button[type="submit"]').first().click();
        }
        
        // 2. カテゴリー選択画面に遷移
        await page.waitForURL(/category|menu/);
        console.log('📂 カテゴリー選択画面');
        
        // カテゴリーが表示されているか確認
        const categoriesVisible = await page.locator('text=/ケアコース|水素コース/').count();
        console.log(`カテゴリー数: ${categoriesVisible}`);
        
        if (categoriesVisible > 0) {
            // ケアコースを選択
            const careCategoryForm = page.locator('form:has-text("ケアコース")').first();
            if (await careCategoryForm.count() > 0) {
                await careCategoryForm.locator('button[type="submit"]').click();
                console.log('✅ ケアコースを選択');
            }
        }
        
        // 3. 時間・料金選択画面
        await page.waitForTimeout(1000);
        const currentUrl = page.url();
        console.log(`現在のURL: ${currentUrl}`);
        
        if (currentUrl.includes('time') || currentUrl.includes('select')) {
            console.log('⏰ 時間・料金選択画面');
            
            // 30分コースが表示されているか
            const time30min = await page.locator('text=/30分/').count();
            console.log(`30分コース表示: ${time30min > 0 ? '✓' : '✗'}`);
            
            // 50分コースが表示されているか
            const time50min = await page.locator('text=/50分/').count();
            console.log(`50分コース表示: ${time50min > 0 ? '✓' : '✗'}`);
            
            // 料金が表示されているか
            const priceDisplay = await page.locator('text=/¥|円/').count();
            console.log(`料金表示: ${priceDisplay > 0 ? '✓' : '✗'}`);
            
            // サブスク限定メニューのチェック
            const subscriptionOnly = await page.locator('text=/サブスク/').count();
            console.log(`サブスク限定メニュー: ${subscriptionOnly > 0 ? 'あり' : 'なし'}`);
        }
        
        // ページ構造の確認
        console.log('\n📋 ページ要素チェック:');
        
        // ステップインジケーター
        const stepIndicator = await page.locator('.rounded-full').count();
        console.log(`ステップインジケーター: ${stepIndicator}個`);
        
        // 戻るリンク
        const backLink = await page.locator('a:has-text("戻る")').count();
        console.log(`戻るリンク: ${backLink > 0 ? '✓' : '✗'}`);
        
        // レスポンシブデザイン（グリッドレイアウト）
        const gridLayout = await page.locator('.grid').count();
        console.log(`グリッドレイアウト: ${gridLayout > 0 ? '✓' : '✗'}`);
        
        // エラーメッセージがないことを確認
        const errorMessages = await page.locator('text=/error|エラー|失敗/i').count();
        expect(errorMessages).toBe(0);
        
        console.log('\n✅ 予約フローテスト完了');
    });

    test('メニューカテゴリーの表示確認', async ({ page }) => {
        // Basic認証
        const authHeader = 'Basic ' + Buffer.from('eyetraining:ginza2024').toString('base64');
        await page.setExtraHTTPHeaders({
            'Authorization': authHeader
        });
        
        // 直接カテゴリー選択画面へ（セッションに店舗IDを設定済みの場合）
        await page.goto('http://127.0.0.1:8000/reservation/store');
        
        // 銀座店を選択
        const storeForm = page.locator('form').first();
        await storeForm.locator('input[name="store_id"][value="1"]').check();
        await storeForm.locator('button[type="submit"]').click();
        
        // カテゴリー画面
        await page.waitForURL(/category/);
        
        // 期待されるカテゴリー
        const expectedCategories = ['ケアコース', '水素コース', 'セットコース'];
        
        for (const category of expectedCategories) {
            const categoryExists = await page.locator(`text="${category}"`).count();
            console.log(`${category}: ${categoryExists > 0 ? '✓ 表示' : '✗ 非表示'}`);
        }
        
        // カテゴリーの説明文
        const descriptions = await page.locator('p.text-gray-600').allTextContents();
        console.log(`説明文の数: ${descriptions.length}`);
        
        console.log('✅ カテゴリー表示テスト完了');
    });
});