import { test, expect } from '@playwright/test';

/**
 * 回数券システムE2Eテスト
 *
 * テスト対象:
 * 1. 回数券プラン作成
 * 2. 顧客への回数券発行
 * 3. 回数券を使った予約作成
 * 4. 予約キャンセルによる回数券返却
 * 5. 利用履歴の確認
 */

test.describe('回数券システム E2E', () => {
    test.beforeEach(async ({ page }) => {
        // 管理画面にログイン
        await page.goto('/admin/login');
        await page.fill('input[name="email"]', 'admin@eye-training.com');
        await page.fill('input[name="password"]', 'password');
        await page.click('button[type="submit"]');
        await page.waitForURL('/admin');
    });

    test('1. 回数券プランを作成できる', async ({ page }) => {
        // 回数券設定ページに移動
        await page.click('text=回数券設定');
        await page.waitForURL(/.*\/admin\/ticket-plans/);

        // 新規作成ボタンをクリック
        await page.click('text=新規作成');
        await page.waitForURL(/.*\/admin\/ticket-plans\/create/);

        // フォーム入力
        await page.selectOption('select[name="store_id"]', { index: 1 });
        await page.fill('input[name="name"]', 'E2Eテスト10回券');
        await page.fill('textarea[name="description"]', 'E2Eテスト用の回数券です');
        await page.fill('input[name="ticket_count"]', '10');
        await page.fill('input[name="price"]', '50000');
        await page.fill('input[name="validity_months"]', '3');

        // 保存
        await page.click('button:has-text("作成")');
        await page.waitForURL(/.*\/admin\/ticket-plans$/);

        // 作成されたプランが表示されることを確認
        await expect(page.locator('text=E2Eテスト10回券')).toBeVisible();
        await expect(page.locator('text=¥50,000')).toBeVisible();
        await expect(page.locator('text=10回')).toBeVisible();
    });

    test('2. 顧客に回数券を発行できる', async ({ page }) => {
        // 顧客管理ページに移動
        await page.click('text=顧客管理');
        await page.waitForURL(/.*\/admin\/customers/);

        // 最初の顧客をクリック
        await page.click('table tbody tr:first-child');
        await page.waitForURL(/.*\/admin\/customers\/\d+/);

        // 回数券タブをクリック
        await page.click('text=回数券');
        await page.waitForTimeout(1000); // タブ切り替え待機

        // 回数券発行ボタンをクリック
        await page.click('text=回数券発行');
        await page.waitForTimeout(500);

        // フォーム入力
        const ticketPlanSelect = page.locator('select[name="ticket_plan_id"]');
        await ticketPlanSelect.waitFor({ state: 'visible' });
        await ticketPlanSelect.selectOption({ index: 1 });

        // 保存
        await page.click('button:has-text("作成")');
        await page.waitForTimeout(1000);

        // 発行された回数券が表示されることを確認
        await expect(page.locator('text=有効')).toBeVisible();
        await expect(page.locator('text=10/10回')).toBeVisible();
    });

    test('3. 回数券を使って予約を作成できる', async ({ page }) => {
        // 予約カレンダーページに移動
        await page.click('text=予約カレンダー');
        await page.waitForURL(/.*\/admin\/reservations/);

        // 新規予約作成
        await page.click('text=新規予約');
        await page.waitForURL(/.*\/admin\/reservations\/create/);

        // 基本情報入力
        await page.selectOption('select[name="store_id"]', { index: 1 });
        await page.waitForTimeout(500);

        // 顧客選択（回数券を持っている顧客）
        const customerSelect = page.locator('select[name="customer_id"]');
        await customerSelect.selectOption({ index: 1 });
        await page.waitForTimeout(500);

        // メニュー選択
        await page.selectOption('select[name="menu_id"]', { index: 1 });

        // 予約日時入力
        const tomorrow = new Date();
        tomorrow.setDate(tomorrow.getDate() + 1);
        const dateString = tomorrow.toISOString().split('T')[0];
        await page.fill('input[name="reservation_date"]', dateString);
        await page.fill('input[name="start_time"]', '10:00');

        // 支払い情報セクションを展開
        await page.click('text=支払い情報');
        await page.waitForTimeout(500);

        // 支払い方法で「回数券」を選択
        await page.selectOption('select[name="payment_method"]', 'ticket');
        await page.waitForTimeout(500);

        // 利用する回数券を選択
        const ticketSelect = page.locator('select[name="customer_ticket_id"]');
        await ticketSelect.waitFor({ state: 'visible' });
        await ticketSelect.selectOption({ index: 1 });

        // 予約作成
        await page.click('button:has-text("作成")');
        await page.waitForURL(/.*\/admin\/reservations$/);

        // 成功通知が表示されることを確認
        await expect(page.locator('text=予約を登録しました')).toBeVisible();
        await expect(page.locator('text=回数券を使用しました')).toBeVisible();
    });

    test('4. 回数券使用後に残回数が減る', async ({ page }) => {
        // 顧客管理→回数券タブで確認
        await page.click('text=顧客管理');
        await page.waitForURL(/.*\/admin\/customers/);
        await page.click('table tbody tr:first-child');
        await page.click('text=回数券');
        await page.waitForTimeout(1000);

        // 残回数が9/10になっていることを確認
        await expect(page.locator('text=9/10回').or(page.locator('text=残り9回'))).toBeVisible();
    });

    test('5. 予約をキャンセルすると回数券が返却される', async ({ page }) => {
        // 予約一覧に移動
        await page.click('text=予約管理');
        await page.waitForURL(/.*\/admin\/reservations/);

        // 回数券で作成した予約を検索
        await page.fill('input[placeholder*="検索"]', '回数券');
        await page.waitForTimeout(500);

        // 最初の予約を編集
        await page.click('table tbody tr:first-child button:has-text("編集")');
        await page.waitForTimeout(1000);

        // ステータスを「キャンセル」に変更
        await page.selectOption('select[name="status"]', 'cancelled');

        // 保存
        await page.click('button:has-text("保存")');
        await page.waitForURL(/.*\/admin\/reservations$/);

        // 顧客の回数券を確認
        await page.click('text=顧客管理');
        await page.waitForURL(/.*\/admin\/customers/);
        await page.click('table tbody tr:first-child');
        await page.click('text=回数券');
        await page.waitForTimeout(1000);

        // 残回数が10/10に戻っていることを確認
        await expect(page.locator('text=10/10回').or(page.locator('text=残り10回'))).toBeVisible();
    });

    test('6. 利用履歴が正しく記録される', async ({ page }) => {
        // 顧客管理→回数券タブ
        await page.click('text=顧客管理');
        await page.waitForURL(/.*\/admin\/customers/);
        await page.click('table tbody tr:first-child');
        await page.click('text=回数券');
        await page.waitForTimeout(1000);

        // 回数券の詳細を表示
        await page.click('table tbody tr:first-child button:has-text("表示")');
        await page.waitForTimeout(1000);

        // 利用履歴セクションを確認
        await page.click('text=利用履歴');
        await page.waitForTimeout(500);

        // 履歴が表示されることを確認
        await expect(page.locator('text=利用日時')).toBeVisible();
    });

    test('7. 期限切れの回数券は予約に使用できない', async ({ page }) => {
        // 新規予約作成
        await page.goto('/admin/reservations/create');

        // 基本情報入力
        await page.selectOption('select[name="store_id"]', { index: 1 });
        await page.waitForTimeout(500);

        // 期限切れ回数券を持つ顧客を選択
        await page.selectOption('select[name="customer_id"]', { index: 2 });
        await page.waitForTimeout(500);

        // 支払い方法で「回数券」を選択
        await page.click('text=支払い情報');
        await page.selectOption('select[name="payment_method"]', 'ticket');
        await page.waitForTimeout(500);

        // 利用可能な回数券が表示されないことを確認
        const ticketSelect = page.locator('select[name="customer_ticket_id"]');
        const options = await ticketSelect.locator('option').count();

        // プレースホルダーのみ（選択肢なし）
        expect(options).toBeLessThanOrEqual(1);
    });

    test('8. 使い切った回数券のステータスが変わる', async ({ page }) => {
        // 顧客管理→回数券タブ
        await page.click('text=顧客管理');
        await page.click('table tbody tr:first-child');
        await page.click('text=回数券');
        await page.waitForTimeout(1000);

        // 回数券を10回使用（手動使用ボタン）
        for (let i = 0; i < 10; i++) {
            await page.click('button:has-text("使用")');
            await page.waitForTimeout(300);
            await page.click('button:has-text("使用する")'); // 確認ダイアログ
            await page.waitForTimeout(500);
        }

        // ステータスが「使い切り」になることを確認
        await page.reload();
        await page.waitForTimeout(1000);
        await expect(page.locator('text=使い切り')).toBeVisible();
        await expect(page.locator('text=0/10回').or(page.locator('text=残り0回'))).toBeVisible();
    });

    test('9. 回数券プランを複製できる', async ({ page }) => {
        // 回数券設定ページ
        await page.click('text=回数券設定');
        await page.waitForURL(/.*\/admin\/ticket-plans/);

        // 複製ボタンをクリック
        await page.click('table tbody tr:first-child button:has-text("複製")');
        await page.waitForTimeout(1000);

        // 複製されたプランが表示されることを確認
        await expect(page.locator('text=(コピー)')).toBeVisible();

        // 複製されたプランは無効状態
        const copyRow = page.locator('tr:has-text("(コピー)")');
        await expect(copyRow.locator('svg[class*="text-danger"]')).toBeVisible(); // 無効アイコン
    });

    test('10. 回数券の手動返却ができる', async ({ page }) => {
        // 顧客管理→回数券タブ
        await page.click('text=顧客管理');
        await page.click('table tbody tr:first-child');
        await page.click('text=回数券');
        await page.waitForTimeout(1000);

        // まず1回使用
        await page.click('button:has-text("使用")');
        await page.click('button:has-text("使用する")');
        await page.waitForTimeout(1000);

        // 残回数を確認（9/10）
        await expect(page.locator('text=9/10回').or(page.locator('text=残り9回'))).toBeVisible();

        // 返却ボタンをクリック
        await page.click('button:has-text("返却")');
        await page.click('button:has-text("返却する")');
        await page.waitForTimeout(1000);

        // 残回数が戻っていることを確認（10/10）
        await expect(page.locator('text=10/10回').or(page.locator('text=残り10回'))).toBeVisible();
    });
});
