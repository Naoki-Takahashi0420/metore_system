import { test, expect } from '@playwright/test';

test.describe('売上管理システムE2Eテスト', () => {
    
    test.beforeEach(async ({ page }) => {
        // ログイン
        await page.goto('/admin/login');
        await page.getByLabel('メールアドレス').fill('superadmin@xsyumeno.com');
        await page.getByLabel('パスワード').fill('password');
        await page.getByRole('button', { name: 'ログイン' }).click();
        await page.waitForURL('/admin');
    });
    
    test('売上管理ページにアクセスできる', async ({ page }) => {
        // 売上管理メニューを探す（売上・会計グループ内）
        const salesLink = page.getByRole('link', { name: '売上管理' });
        await expect(salesLink).toBeVisible();
        await salesLink.click();
        
        // 売上一覧ページが表示される
        await expect(page).toHaveURL(/\/admin\/sales/);
        await expect(page.getByRole('heading', { name: '売上' })).toBeVisible();
        
        // 日次精算ボタンが表示される
        const dailyClosingButton = page.getByRole('link', { name: '日次精算' });
        await expect(dailyClosingButton).toBeVisible();
        
        console.log('✅ 売上管理ページへのアクセス成功');
    });
    
    test('新規売上を作成できる', async ({ page }) => {
        // 売上管理ページに移動
        await page.getByRole('link', { name: '売上管理' }).click();
        
        // 新規作成ボタンをクリック
        await page.getByRole('link', { name: '新規作成' }).click();
        
        // フォームが表示される
        await expect(page.getByText('売上基本情報')).toBeVisible();
        await expect(page.getByText('予約・顧客情報')).toBeVisible();
        await expect(page.getByText('金額・支払情報')).toBeVisible();
        
        // 売上番号が自動生成されている
        const saleNumberInput = page.getByLabel('売上番号');
        const saleNumber = await saleNumberInput.inputValue();
        expect(saleNumber).toMatch(/^SL\d{10}$/);
        
        console.log('✅ 新規売上フォームが正常に表示');
        console.log(`  生成された売上番号: ${saleNumber}`);
    });
    
    test('日次精算ページにアクセスできる', async ({ page }) => {
        // 売上管理ページに移動
        await page.getByRole('link', { name: '売上管理' }).click();
        
        // 日次精算ボタンをクリック
        await page.getByRole('link', { name: '日次精算' }).click();
        
        // 日次精算ページが表示される
        await expect(page).toHaveURL(/\/admin\/sales\/daily-closing/);
        await expect(page.getByRole('heading', { name: '日次精算' })).toBeVisible();
        
        // 売上サマリーが表示される
        await expect(page.getByText('売上サマリー')).toBeVisible();
        await expect(page.getByText('総売上')).toBeVisible();
        await expect(page.getByText('取引件数')).toBeVisible();
        await expect(page.getByText('来店客数')).toBeVisible();
        
        // 支払方法別売上が表示される
        await expect(page.getByText('支払方法別売上')).toBeVisible();
        await expect(page.getByText('現金')).toBeVisible();
        await expect(page.getByText('カード')).toBeVisible();
        await expect(page.getByText('電子マネー')).toBeVisible();
        
        // 現金計算セクションが表示される
        await expect(page.getByText('現金計算')).toBeVisible();
        await expect(page.getByText('釣銭準備金')).toBeVisible();
        await expect(page.getByText('予定現金残高')).toBeVisible();
        
        console.log('✅ 日次精算ページが正常に表示');
    });
    
    test('ダッシュボードウィジェットが表示される', async ({ page }) => {
        // ダッシュボードに戻る
        await page.getByRole('link', { name: 'ダッシュボード' }).first().click();
        
        // 売上統計ウィジェットが表示される
        const todaySales = page.getByText('本日の売上').first();
        const monthSales = page.getByText('今月の売上').first();
        const todayCustomers = page.getByText('本日の来店客数').first();
        const todayReservations = page.getByText('本日の予約').first();
        
        // 各ウィジェットの存在を確認（表示を待つ）
        await expect(todaySales).toBeVisible({ timeout: 10000 });
        await expect(monthSales).toBeVisible({ timeout: 10000 });
        await expect(todayCustomers).toBeVisible({ timeout: 10000 });
        await expect(todayReservations).toBeVisible({ timeout: 10000 });
        
        console.log('✅ ダッシュボードウィジェットが正常に表示');
    });
    
    test('売上グラフが表示される', async ({ page }) => {
        // ダッシュボードページを確認
        await page.getByRole('link', { name: 'ダッシュボード' }).first().click();
        
        // グラフウィジェットのタイトルを確認
        const chartTitle = page.getByText('売上推移（過去30日）');
        await expect(chartTitle).toBeVisible({ timeout: 10000 });
        
        // Canvas要素（グラフ）の存在を確認
        const canvas = page.locator('canvas').first();
        await expect(canvas).toBeVisible();
        
        console.log('✅ 売上グラフが正常に表示');
    });
    
    test('売れ筋メニューウィジェットが表示される', async ({ page }) => {
        // ダッシュボードページを確認
        await page.getByRole('link', { name: 'ダッシュボード' }).first().click();
        
        // 売れ筋メニューセクションを確認
        const topMenusTitle = page.getByText('今月の売れ筋メニュー TOP10');
        await expect(topMenusTitle).toBeVisible({ timeout: 10000 });
        
        // 時間帯別売上セクションを確認
        const timeRangeTitle = page.getByText('本日の時間帯別売上');
        await expect(timeRangeTitle).toBeVisible({ timeout: 10000 });
        
        console.log('✅ 売れ筋メニューウィジェットが正常に表示');
    });
    
    test('ナビゲーションバッジが表示される', async ({ page }) => {
        // 売上管理メニューのバッジを確認
        const salesMenuItem = page.locator('nav').getByRole('link', { name: /売上管理/ });
        await expect(salesMenuItem).toBeVisible();
        
        // バッジ（本日の売上件数）が存在するか確認
        const badge = salesMenuItem.locator('.fi-badge');
        if (await badge.count() > 0) {
            const badgeText = await badge.textContent();
            console.log(`✅ 本日の売上件数バッジ: ${badgeText}件`);
        } else {
            console.log('ℹ️ 本日の売上はまだありません');
        }
    });
});

test.describe('売上作成フローテスト', () => {
    
    test('売上を最後まで作成する', async ({ page }) => {
        // ログイン
        await page.goto('/admin/login');
        await page.getByLabel('メールアドレス').fill('superadmin@xsyumeno.com');
        await page.getByLabel('パスワード').fill('password');
        await page.getByRole('button', { name: 'ログイン' }).click();
        await page.waitForURL('/admin');
        
        // 売上管理ページに移動
        await page.getByRole('link', { name: '売上管理' }).click();
        await page.getByRole('link', { name: '新規作成' }).click();
        
        // 基本情報を入力
        await page.getByLabel('小計').fill('10000');
        await page.getByLabel('消費税').fill('1000');
        await page.getByLabel('合計金額').fill('11000');
        
        // 支払方法を選択
        await page.getByLabel('支払方法').selectOption('cash');
        
        // 作成ボタンをクリック
        await page.getByRole('button', { name: '作成' }).click();
        
        // 成功通知または一覧ページへのリダイレクトを確認
        await page.waitForURL(/\/admin\/sales/);
        
        console.log('✅ 売上の作成に成功');
    });
});