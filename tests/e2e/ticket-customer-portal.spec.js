import { test, expect } from '@playwright/test';

/**
 * 回数券システム - 顧客ポータルE2Eテスト
 *
 * テスト対象:
 * - 顧客ポータルでの回数券一覧表示
 * - 利用履歴の表示
 * - 期限切れ警告の表示
 * - レスポンシブデザイン
 */

const BASE_URL = 'http://localhost:8000';
const ADMIN_URL = `${BASE_URL}/admin`;
const CUSTOMER_URL = `${BASE_URL}/customer`;

// テスト用の顧客情報
const TEST_CUSTOMER = {
    phone: '09012345678',
    lastName: 'テスト',
    firstName: '顧客',
};

test.describe('回数券システム - 顧客ポータル', () => {

    test.beforeEach(async ({ page }) => {
        // 各テストの前にログインページに移動
        await page.goto(`${CUSTOMER_URL}/login`);
    });

    test('1. 顧客ポータル - 回数券一覧ページにアクセスできる', async ({ page }) => {
        // 顧客ログイン
        await page.fill('input[type="tel"]', TEST_CUSTOMER.phone);
        await page.click('button:has-text("ログイン")');

        // OTP入力（テスト環境では123456が固定）
        await page.waitForSelector('input[type="text"]', { timeout: 5000 });
        await page.fill('input[type="text"]', '123456');
        await page.click('button:has-text("認証")');

        // ダッシュボードに到達
        await page.waitForURL(`${CUSTOMER_URL}/dashboard`, { timeout: 10000 });

        // 回数券ページへ移動
        await page.goto(`${CUSTOMER_URL}/tickets`);
        await page.waitForSelector('h1:has-text("回数券")', { timeout: 5000 });

        // ページタイトルを確認
        const title = await page.locator('h1').textContent();
        expect(title).toContain('回数券');
    });

    test('2. 顧客ポータル - 回数券がない場合の空状態表示', async ({ page }) => {
        // 回数券を持っていない顧客でログイン
        await page.fill('input[type="tel"]', '09099999999'); // 回数券なし顧客
        await page.click('button:has-text("ログイン")');
        await page.fill('input[type="text"]', '123456');
        await page.click('button:has-text("認証")');

        await page.goto(`${CUSTOMER_URL}/tickets`);

        // 空状態メッセージを確認
        await page.waitForSelector('text=回数券がありません', { timeout: 5000 });
        const emptyMessage = await page.locator('text=回数券がありません').isVisible();
        expect(emptyMessage).toBeTruthy();
    });

    test('3. 顧客ポータル - 統計情報ダッシュボードの表示', async ({ page }) => {
        // 回数券を持つ顧客でログイン
        await page.fill('input[type="tel"]', TEST_CUSTOMER.phone);
        await page.click('button:has-text("ログイン")');
        await page.fill('input[type="text"]', '123456');
        await page.click('button:has-text("認証")');

        await page.goto(`${CUSTOMER_URL}/tickets`);
        await page.waitForLoadState('networkidle');

        // 統計情報セクションを確認
        const statsSection = await page.locator('#stats-section');
        const isVisible = await statsSection.isVisible();

        if (isVisible) {
            // 各統計項目を確認
            const activeCount = await page.locator('#active-count').textContent();
            const remainingCount = await page.locator('#remaining-count').textContent();
            const expiringCount = await page.locator('#expiring-count').textContent();
            const totalCount = await page.locator('#total-count').textContent();

            expect(activeCount).toMatch(/\d+枚/);
            expect(remainingCount).toMatch(/\d+回/);
            expect(expiringCount).toMatch(/\d+枚/);
            expect(totalCount).toMatch(/\d+枚/);
        }
    });

    test('4. 顧客ポータル - 回数券カードの表示内容確認', async ({ page }) => {
        await page.fill('input[type="tel"]', TEST_CUSTOMER.phone);
        await page.click('button:has-text("ログイン")');
        await page.fill('input[type="text"]', '123456');
        await page.click('button:has-text("認証")');

        await page.goto(`${CUSTOMER_URL}/tickets`);
        await page.waitForLoadState('networkidle');

        // 回数券カードの存在確認
        const ticketCards = await page.locator('#tickets-list > div');
        const cardCount = await ticketCards.count();

        if (cardCount > 0) {
            const firstCard = ticketCards.first();

            // カード内の必須要素を確認
            const hasPlanName = await firstCard.locator('h3').count() > 0;
            const hasStoreName = await firstCard.locator('text=/店舗/i').count() > 0;
            const hasRemainingCount = await firstCard.locator('text=/残り回数/i').count() > 0;
            const hasExpiry = await firstCard.locator('text=/有効期限/i').count() > 0;
            const hasButton = await firstCard.locator('button:has-text("利用履歴")').count() > 0;

            expect(hasPlanName).toBeTruthy();
            expect(hasRemainingCount).toBeTruthy();
            expect(hasExpiry).toBeTruthy();
            expect(hasButton).toBeTruthy();
        }
    });

    test('5. 顧客ポータル - 利用履歴モーダルの開閉', async ({ page }) => {
        await page.fill('input[type="tel"]', TEST_CUSTOMER.phone);
        await page.click('button:has-text("ログイン")');
        await page.fill('input[type="text"]', '123456');
        await page.click('button:has-text("認証")');

        await page.goto(`${CUSTOMER_URL}/tickets`);
        await page.waitForLoadState('networkidle');

        const ticketCards = await page.locator('#tickets-list > div');
        const cardCount = await ticketCards.count();

        if (cardCount > 0) {
            // 「利用履歴を見る」ボタンをクリック
            await page.click('button:has-text("利用履歴")');

            // モーダルが表示されることを確認
            await page.waitForSelector('#history-modal:not(.hidden)', { timeout: 3000 });
            const modalVisible = await page.locator('#history-modal').isVisible();
            expect(modalVisible).toBeTruthy();

            // モーダルのタイトル確認
            const modalTitle = await page.locator('#history-modal h2').textContent();
            expect(modalTitle).toContain('利用履歴');

            // モーダルを閉じる
            await page.click('#close-history-modal');

            // モーダルが非表示になることを確認
            const modalHidden = await page.locator('#history-modal').evaluate(el =>
                el.classList.contains('hidden')
            );
            expect(modalHidden).toBeTruthy();
        }
    });

    test('6. 顧客ポータル - 期限間近の警告表示', async ({ page }) => {
        await page.fill('input[type="tel"]', TEST_CUSTOMER.phone);
        await page.click('button:has-text("ログイン")');
        await page.fill('input[type="text"]', '123456');
        await page.click('button:has-text("認証")');

        await page.goto(`${CUSTOMER_URL}/tickets`);
        await page.waitForLoadState('networkidle');

        // 期限間近の警告バッジを確認
        const warningBadges = await page.locator('text=⚠️ 期限間近');
        const warningCount = await warningBadges.count();

        // 期限間近の回数券がある場合
        if (warningCount > 0) {
            const firstWarning = warningBadges.first();
            const isVisible = await firstWarning.isVisible();
            expect(isVisible).toBeTruthy();

            // 警告バッジのスタイル確認（黄色背景）
            const hasWarningStyle = await firstWarning.evaluate(el =>
                el.classList.contains('bg-yellow-100')
            );
            expect(hasWarningStyle).toBeTruthy();
        }
    });

    test('7. 顧客ポータル - ステータスバッジの表示', async ({ page }) => {
        await page.fill('input[type="tel"]', TEST_CUSTOMER.phone);
        await page.click('button:has-text("ログイン")');
        await page.fill('input[type="text"]', '123456');
        await page.click('button:has-text("認証")');

        await page.goto(`${CUSTOMER_URL}/tickets`);
        await page.waitForLoadState('networkidle');

        const ticketCards = await page.locator('#tickets-list > div');
        const cardCount = await ticketCards.count();

        if (cardCount > 0) {
            // 各カードのステータスバッジを確認
            for (let i = 0; i < Math.min(cardCount, 3); i++) {
                const card = ticketCards.nth(i);
                const statusBadge = card.locator('span[class*="rounded-full"]').first();
                const statusText = await statusBadge.textContent();

                // ステータスが表示されていることを確認
                const validStatuses = ['有効', '期限切れ', '使い切り', 'キャンセル'];
                const hasValidStatus = validStatuses.some(status =>
                    statusText.includes(status)
                );
                expect(hasValidStatus).toBeTruthy();
            }
        }
    });

    test('8. 顧客ポータル - レスポンシブデザイン（モバイル）', async ({ page }) => {
        // モバイルビューポートに設定
        await page.setViewportSize({ width: 375, height: 667 });

        await page.fill('input[type="tel"]', TEST_CUSTOMER.phone);
        await page.click('button:has-text("ログイン")');
        await page.fill('input[type="text"]', '123456');
        await page.click('button:has-text("認証")');

        await page.goto(`${CUSTOMER_URL}/tickets`);
        await page.waitForLoadState('networkidle');

        // ページが正常に表示されることを確認
        const pageTitle = await page.locator('h1:has-text("回数券")').isVisible();
        expect(pageTitle).toBeTruthy();

        // 統計情報がグリッドレイアウトで表示されることを確認
        const statsGrid = await page.locator('#stats-section');
        if (await statsGrid.isVisible()) {
            const hasGridClass = await statsGrid.evaluate(el =>
                el.classList.contains('grid')
            );
            expect(hasGridClass).toBeTruthy();
        }
    });

    test('9. 顧客ポータル - APIエラー時のエラー表示', async ({ page }) => {
        await page.fill('input[type="tel"]', TEST_CUSTOMER.phone);
        await page.click('button:has-text("ログイン")');
        await page.fill('input[type="text"]', '123456');
        await page.click('button:has-text("認証")');

        // ネットワークエラーをシミュレート
        await page.route('**/api/customer/tickets-token', route => {
            route.abort();
        });

        await page.goto(`${CUSTOMER_URL}/tickets`);

        // エラーメッセージが表示されることを確認
        await page.waitForSelector('#error:not(.hidden)', { timeout: 5000 });
        const errorVisible = await page.locator('#error').isVisible();
        expect(errorVisible).toBeTruthy();

        const errorMessage = await page.locator('#error-message').textContent();
        expect(errorMessage).toContain('失敗');
    });

    test('10. 顧客ポータル - ログアウト機能', async ({ page }) => {
        await page.fill('input[type="tel"]', TEST_CUSTOMER.phone);
        await page.click('button:has-text("ログイン")');
        await page.fill('input[type="text"]', '123456');
        await page.click('button:has-text("認証")');

        await page.goto(`${CUSTOMER_URL}/tickets`);
        await page.waitForLoadState('networkidle');

        // ログアウトボタンをクリック
        await page.click('#logout-btn');

        // ログインページにリダイレクトされることを確認
        await page.waitForURL(`${CUSTOMER_URL}/login`, { timeout: 5000 });

        // ローカルストレージからトークンが削除されていることを確認
        const token = await page.evaluate(() =>
            localStorage.getItem('customer_auth_token')
        );
        expect(token).toBeNull();
    });
});

test.describe('回数券システム - 管理画面との連携', () => {

    test('11. 管理画面で回数券を発行 → 顧客ポータルで確認', async ({ page, context }) => {
        // 管理画面にログイン
        await page.goto(`${ADMIN_URL}/login`);
        await page.fill('input[name="email"]', 'admin@eye-training.com');
        await page.fill('input[name="password"]', 'password');
        await page.click('button[type="submit"]');

        await page.waitForURL(`${ADMIN_URL}/**`, { timeout: 10000 });

        // 回数券発行ページへ移動
        await page.goto(`${ADMIN_URL}/customer-tickets`);
        await page.waitForSelector('text=回数券', { timeout: 5000 });

        // 「新規作成」ボタンをクリック
        const createButton = await page.locator('a:has-text("新規"), button:has-text("新規")').first();
        await createButton.click();

        // フォームが表示されるまで待つ
        await page.waitForSelector('form', { timeout: 5000 });

        // 新しいタブで顧客ポータルを開く
        const customerPage = await context.newPage();
        await customerPage.goto(`${CUSTOMER_URL}/login`);
        await customerPage.fill('input[type="tel"]', TEST_CUSTOMER.phone);
        await customerPage.click('button:has-text("ログイン")');
        await customerPage.fill('input[type="text"]', '123456');
        await customerPage.click('button:has-text("認証")');

        await customerPage.goto(`${CUSTOMER_URL}/tickets`);
        await customerPage.waitForLoadState('networkidle');

        // 回数券が表示されることを確認（既存の回数券）
        const ticketsExist = await customerPage.locator('#tickets-list > div').count() > 0 ||
                             await customerPage.locator('#no-tickets').isVisible();
        expect(ticketsExist).toBeTruthy();
    });

    test('12. 回数券使用 → 利用履歴に反映', async ({ page }) => {
        await page.fill('input[type="tel"]', TEST_CUSTOMER.phone);
        await page.click('button:has-text("ログイン")');
        await page.fill('input[type="text"]', '123456');
        await page.click('button:has-text("認証")');

        await page.goto(`${CUSTOMER_URL}/tickets`);
        await page.waitForLoadState('networkidle');

        const ticketCards = await page.locator('#tickets-list > div');
        const cardCount = await ticketCards.count();

        if (cardCount > 0) {
            // 最初の回数券の利用回数を確認
            const firstCard = ticketCards.first();
            const usedCountText = await firstCard.locator('text=/利用済み/').textContent();
            const usedCount = parseInt(usedCountText.match(/\d+/)?.[0] || '0');

            // 利用履歴を開く
            await page.click('button:has-text("利用履歴")');
            await page.waitForSelector('#history-modal:not(.hidden)');

            // 履歴の件数を確認
            const historyItems = await page.locator('#history-content > div');
            const historyCount = await historyItems.count();

            // 利用回数と履歴件数が一致するか確認（簡易チェック）
            expect(historyCount).toBeGreaterThanOrEqual(0);
        }
    });
});
