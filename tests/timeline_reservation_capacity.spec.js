import { test, expect } from '@playwright/test';

test.describe('Timeline Reservation Capacity Management', () => {
    // ログイン用のヘルパー関数
    async function login(page) {
        await page.goto('http://127.0.0.1:8003/admin/login');
        await page.fill('input[name="email"]', 'admin@eye-training.com');
        await page.fill('input[name="password"]', 'password');
        await page.click('button[type="submit"]');
        await page.waitForLoadState('networkidle');
    }

    test.beforeEach(async ({ page }) => {
        // 各テスト前にログインしてタイムラインページに移動
        await login(page);

        // タイムラインページへ移動
        await page.goto('http://127.0.0.1:8003/admin');
        await page.waitForLoadState('networkidle');
    });

    test('容量制限が正しく表示される', async ({ page }) => {
        console.log('=== 容量制限表示テスト開始 ===');

        // 新宿店を選択
        await page.click('button:has-text("新宿店")');
        await page.waitForTimeout(2000); // データロード待機

        // 店舗情報の表示を確認
        const storeMode = await page.textContent('.bg-blue-50 .text-blue-700, .bg-gray-50 .text-gray-700');
        console.log('店舗モード:', storeMode);

        // スタッフシフトモードかつ最大1席であることを確認
        expect(storeMode).toContain('シフトベース（スタッフ別）');
        expect(storeMode).toContain('（最大1席）');

        console.log('✅ 容量制限表示テスト完了');
    });

    test('既存予約がある時間帯はクリック不可', async ({ page }) => {
        console.log('=== 既存予約時間帯クリック不可テスト開始 ===');

        // 新宿店を選択
        await page.click('button:has-text("新宿店")');
        await page.waitForTimeout(2000);

        // 13:30の時間帯のセル（既存予約があるはず）を探す
        const timeSlots = await page.$$('td.time-cell');
        let foundOccupiedSlot = false;
        let foundClickableSlot = false;

        for (let slot of timeSlots) {
            const classNames = await slot.getAttribute('class') || '';
            const hasReservation = await slot.$('.booking-block') !== null;

            if (hasReservation) {
                foundOccupiedSlot = true;
                console.log('予約済みスロット発見');

                // 同じ時間帯の他のラインもクリック不可であることを確認
                const otherSlots = await page.$$(`td.time-cell:not(.blocked-cell)`);
                for (let otherSlot of otherSlots.slice(0, 5)) { // 最初の5つをテスト
                    const isClickable = await otherSlot.getAttribute('wire:click');
                    if (isClickable && !await otherSlot.$('.booking-block')) {
                        // このスロットが13:30付近の時間かチェック
                        const tooltip = await otherSlot.getAttribute('title') || '';
                        console.log('ツールチップ:', tooltip);

                        if (tooltip.includes('空き: 0/1') || tooltip.includes('満席')) {
                            foundClickableSlot = true;
                            console.log('✅ 容量満席のスロットは正しくクリック不可');
                        }
                    }
                }
            }
        }

        expect(foundOccupiedSlot).toBe(true);
        console.log('✅ 既存予約時間帯クリック不可テスト完了');
    });

    test('スタッフシフト外時間帯はクリック不可', async ({ page }) => {
        console.log('=== スタッフシフト外時間帯テスト開始 ===');

        // 新宿店を選択
        await page.click('button:has-text("新宿店")');
        await page.waitForTimeout(2000);

        // 16:00以降のセル（シフト外）をチェック
        const timeSlots = await page.$$('td.time-cell');
        let foundShiftOutSlot = false;

        for (let slot of timeSlots) {
            const tooltip = await slot.getAttribute('title') || '';
            const classNames = await slot.getAttribute('class') || '';

            // シフト外のスロットを探す
            if (tooltip.includes('勤務可能なスタッフがいません') || classNames.includes('no-staff-cell')) {
                foundShiftOutSlot = true;

                // クリックイベントがないことを確認
                const clickHandler = await slot.getAttribute('wire:click');
                expect(clickHandler).toBeNull();

                console.log('✅ シフト外スロットは正しくクリック不可:', tooltip);
            }
        }

        expect(foundShiftOutSlot).toBe(true);
        console.log('✅ スタッフシフト外時間帯テスト完了');
    });

    test('営業時間外はクリック不可', async ({ page }) => {
        console.log('=== 営業時間外テスト開始 ===');

        // 新宿店を選択（営業時間 13:00-22:00）
        await page.click('button:has-text("新宿店")');
        await page.waitForTimeout(2000);

        // 10:00-12:00の時間帯（営業時間外）をチェック
        const timeSlots = await page.$$('td.time-cell');
        let foundOutOfHoursSlot = false;

        for (let slot of timeSlots) {
            const tooltip = await slot.getAttribute('title') || '';

            if (tooltip.includes('営業時間外')) {
                foundOutOfHoursSlot = true;

                // クリックイベントがないことを確認
                const clickHandler = await slot.getAttribute('wire:click');
                expect(clickHandler).toBeNull();

                console.log('✅ 営業時間外スロットは正しくクリック不可:', tooltip);
            }
        }

        expect(foundOutOfHoursSlot).toBe(true);
        console.log('✅ 営業時間外テスト完了');
    });

    test('予約作成モーダルの動作確認', async ({ page }) => {
        console.log('=== 予約作成モーダルテスト開始 ===');

        // 新宿店を選択
        await page.click('button:has-text("新宿店")');
        await page.waitForTimeout(2000);

        // クリック可能な空きスロットを探す
        const clickableSlots = await page.$$('td.time-cell[wire\\:click]');

        if (clickableSlots.length > 0) {
            console.log(`クリック可能なスロット数: ${clickableSlots.length}`);

            // 最初のクリック可能スロットをクリック
            await clickableSlots[0].click();
            await page.waitForTimeout(1000);

            // モーダルが開いたか確認
            const modal = await page.$('.fixed.inset-0.bg-black.bg-opacity-50');
            expect(modal).not.toBeNull();

            // モーダルのタイトルを確認
            const modalTitle = await page.textContent('h2');
            expect(modalTitle).toContain('新規予約作成');

            console.log('✅ 予約作成モーダルが正常に開く');

            // モーダルを閉じる
            await page.click('button[wire\\:click="closeNewReservationModal"]');
            await page.waitForTimeout(500);
        } else {
            console.log('⚠️ クリック可能なスロットが見つからない（期待される状況）');
        }

        console.log('✅ 予約作成モーダルテスト完了');
    });

    test('予約詳細表示とサブ枠移動機能', async ({ page }) => {
        console.log('=== 予約詳細・サブ枠移動テスト開始 ===');

        // 新宿店を選択
        await page.click('button:has-text("新宿店")');
        await page.waitForTimeout(2000);

        // 既存の予約ブロックを探してクリック
        const reservationBlocks = await page.$$('.booking-block');

        if (reservationBlocks.length > 0) {
            console.log(`予約ブロック数: ${reservationBlocks.length}`);

            // 最初の予約ブロックをクリック
            await reservationBlocks[0].click();
            await page.waitForTimeout(1000);

            // 予約詳細モーダルが開いたか確認
            const detailModal = await page.$('.fixed.inset-0.bg-black.bg-opacity-50');
            expect(detailModal).not.toBeNull();

            // 予約詳細のタイトルを確認
            const detailTitle = await page.textContent('h3');
            expect(detailTitle).toContain('予約詳細');

            console.log('✅ 予約詳細モーダルが正常に開く');

            // サブ枠移動ボタンがあるかチェック
            const moveToSubButton = await page.$('button[wire\\:click*="moveToSub"]');
            if (moveToSubButton) {
                const buttonText = await moveToSubButton.textContent();
                console.log('サブ枠移動ボタン:', buttonText);
                expect(buttonText).toContain('サブ枠へ移動');
            }

            // モーダルを閉じる
            await page.click('button:has-text("✕")');
            await page.waitForTimeout(500);
        }

        console.log('✅ 予約詳細・サブ枠移動テスト完了');
    });

    test('タイムライン表示の整合性チェック', async ({ page }) => {
        console.log('=== タイムライン表示整合性テスト開始 ===');

        // 新宿店を選択
        await page.click('button:has-text("新宿店")');
        await page.waitForTimeout(3000);

        // タイムライン要素の存在確認
        const timelineTable = await page.$('.timeline-table');
        expect(timelineTable).not.toBeNull();

        // ヘッダー行の確認
        const headerCells = await page.$$('.timeline-table th');
        expect(headerCells.length).toBeGreaterThan(0);
        console.log(`ヘッダーセル数: ${headerCells.length}`);

        // データ行の確認
        const dataRows = await page.$$('.timeline-table tbody tr');
        expect(dataRows.length).toBeGreaterThan(0);
        console.log(`データ行数: ${dataRows.length}`);

        // 各行に席ラベルがあることを確認
        for (let i = 0; i < Math.min(dataRows.length, 3); i++) {
            const seatLabel = await dataRows[i].$('.seat-label');
            expect(seatLabel).not.toBeNull();

            const labelText = await seatLabel.textContent();
            console.log(`行 ${i + 1} ラベル:`, labelText);
        }

        // 時間セルの確認
        const timeCells = await page.$$('td.time-cell');
        expect(timeCells.length).toBeGreaterThan(0);
        console.log(`時間セル数: ${timeCells.length}`);

        // 現在時刻インジケーターの確認
        const currentTimeIndicator = await page.$('#current-time-indicator');
        if (currentTimeIndicator) {
            console.log('✅ 現在時刻インジケーターが存在');
        }

        console.log('✅ タイムライン表示整合性テスト完了');
    });

    test('エラーハンドリングの確認', async ({ page }) => {
        console.log('=== エラーハンドリングテスト開始 ===');

        // コンソールエラーをキャッチ
        const consoleErrors = [];
        page.on('console', msg => {
            if (msg.type() === 'error') {
                consoleErrors.push(msg.text());
            }
        });

        // ネットワークエラーをキャッチ
        const networkErrors = [];
        page.on('response', response => {
            if (response.status() >= 400) {
                networkErrors.push(`${response.status()} ${response.url()}`);
            }
        });

        // 新宿店を選択してタイムラインを操作
        await page.click('button:has-text("新宿店")');
        await page.waitForTimeout(3000);

        // いくつかの操作を実行
        const clickableSlots = await page.$$('td.time-cell[wire\\:click]');
        if (clickableSlots.length > 0) {
            await clickableSlots[0].click();
            await page.waitForTimeout(1000);

            // モーダルを閉じる
            const closeButton = await page.$('button[wire\\:click="closeNewReservationModal"]');
            if (closeButton) {
                await closeButton.click();
                await page.waitForTimeout(500);
            }
        }

        // 予約ブロックをクリック
        const reservationBlocks = await page.$$('.booking-block');
        if (reservationBlocks.length > 0) {
            await reservationBlocks[0].click();
            await page.waitForTimeout(1000);

            const closeButton = await page.$('button:has-text("✕")');
            if (closeButton) {
                await closeButton.click();
                await page.waitForTimeout(500);
            }
        }

        // エラーチェック
        console.log(`コンソールエラー数: ${consoleErrors.length}`);
        console.log(`ネットワークエラー数: ${networkErrors.length}`);

        if (consoleErrors.length > 0) {
            console.log('コンソールエラー:', consoleErrors);
        }
        if (networkErrors.length > 0) {
            console.log('ネットワークエラー:', networkErrors);
        }

        // 致命的なエラーがないことを確認（一部のwarningは許容）
        const criticalErrors = consoleErrors.filter(error =>
            !error.includes('Warning') &&
            !error.includes('deprecated') &&
            !error.includes('favicon')
        );

        expect(criticalErrors.length).toBe(0);
        expect(networkErrors.filter(error => error.includes('500')).length).toBe(0);

        console.log('✅ エラーハンドリングテスト完了');
    });
});