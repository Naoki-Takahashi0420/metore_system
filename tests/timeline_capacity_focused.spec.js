import { test, expect } from '@playwright/test';

test.describe('Timeline Capacity Management - Focused Tests', () => {
    // ログイン用のヘルパー関数
    async function login(page) {
        await page.goto('http://127.0.0.1:8003/admin/login');
        await page.fill('#data\\.email', 'admin@eye-training.com');
        await page.fill('input[wire\\:model="data.password"]', 'password');
        await page.click('button[type="submit"]:has-text("ログイン")');
        await page.waitForLoadState('networkidle');
    }

    test.beforeEach(async ({ page }) => {
        await login(page);
        await page.goto('http://127.0.0.1:8003/admin');
        await page.waitForLoadState('networkidle');
    });

    test('新宿店の容量制限表示確認', async ({ page }) => {
        console.log('=== 新宿店容量制限表示テスト開始 ===');

        // 新宿店を選択（ボタンまたは選択肢から）
        try {
            await page.click('button:has-text("新宿店")', { timeout: 5000 });
        } catch {
            // ドロップダウンの場合
            await page.selectOption('select', { label: '新宿店' });
        }

        await page.waitForTimeout(3000); // Livewireの更新を待機

        // ページにタイムラインテーブルが表示されることを確認
        const timelineTable = await page.$('.timeline-table');
        expect(timelineTable).not.toBeNull();

        // スタッフベースモードの表示を確認
        const modeInfo = await page.textContent('.text-blue-700, .text-gray-700');
        console.log('表示されたモード情報:', modeInfo);

        // 容量情報の確認
        const pageText = await page.textContent('body');
        const hasCapacityInfo = pageText.includes('最大1席') || pageText.includes('容量') || pageText.includes('シフトベース');
        console.log('容量情報が表示されている:', hasCapacityInfo);

        console.log('✅ 容量制限表示テスト完了');
    });

    test('タイムラインの基本構造確認', async ({ page }) => {
        console.log('=== タイムライン基本構造テスト開始 ===');

        // 新宿店を選択
        try {
            await page.click('button:has-text("新宿店")', { timeout: 5000 });
        } catch {
            await page.selectOption('select', { label: '新宿店' });
        }

        await page.waitForTimeout(3000);

        // タイムライン要素の存在確認
        const timelineTable = await page.$('.timeline-table');
        expect(timelineTable).not.toBeNull();

        // ヘッダー行の確認
        const headerCells = await page.$$('.timeline-table th');
        console.log(`ヘッダーセル数: ${headerCells.length}`);
        expect(headerCells.length).toBeGreaterThan(0);

        // データ行の確認
        const dataRows = await page.$$('.timeline-table tbody tr');
        console.log(`データ行数: ${dataRows.length}`);
        expect(dataRows.length).toBeGreaterThan(0);

        // 時間セルの確認
        const timeCells = await page.$$('td.time-cell');
        console.log(`時間セル数: ${timeCells.length}`);
        expect(timeCells.length).toBeGreaterThan(0);

        console.log('✅ タイムライン基本構造テスト完了');
    });

    test('予約ブロックの表示確認', async ({ page }) => {
        console.log('=== 予約ブロック表示テスト開始 ===');

        // 新宿店を選択
        try {
            await page.click('button:has-text("新宿店")', { timeout: 5000 });
        } catch {
            await page.selectOption('select', { label: '新宿店' });
        }

        await page.waitForTimeout(3000);

        // 既存の予約ブロックを探す
        const reservationBlocks = await page.$$('.booking-block');
        console.log(`予約ブロック数: ${reservationBlocks.length}`);

        if (reservationBlocks.length > 0) {
            // 最初の予約ブロックの情報を確認
            const firstBlock = reservationBlocks[0];
            const blockText = await firstBlock.textContent();
            console.log('予約ブロックの内容:', blockText);

            // 予約ブロックがクリック可能であることを確認
            const isClickable = await firstBlock.getAttribute('wire:click') !== null;
            console.log('予約ブロックはクリック可能:', isClickable);
        }

        console.log('✅ 予約ブロック表示テスト完了');
    });

    test('空きスロットの判定確認', async ({ page }) => {
        console.log('=== 空きスロット判定テスト開始 ===');

        // 新宿店を選択
        try {
            await page.click('button:has-text("新宿店")', { timeout: 5000 });
        } catch {
            await page.selectOption('select', { label: '新宿店' });
        }

        await page.waitForTimeout(3000);

        // 全時間セルをチェック
        const timeCells = await page.$$('td.time-cell');
        let clickableSlots = 0;
        let nonClickableSlots = 0;
        let slotsWithTooltips = 0;

        for (let i = 0; i < Math.min(timeCells.length, 50); i++) { // 最初の50セルをチェック
            const cell = timeCells[i];
            const hasClickEvent = await cell.getAttribute('wire:click') !== null;
            const tooltip = await cell.getAttribute('title') || '';
            const classNames = await cell.getAttribute('class') || '';

            if (hasClickEvent) {
                clickableSlots++;
            } else {
                nonClickableSlots++;
            }

            if (tooltip) {
                slotsWithTooltips++;
            }

            // 初回ログで詳細を出力
            if (i < 5) {
                console.log(`セル ${i + 1}: クリック可=${hasClickEvent}, ツールチップ="${tooltip}", クラス="${classNames}"`);
            }
        }

        console.log(`クリック可能なスロット: ${clickableSlots}`);
        console.log(`クリック不可なスロット: ${nonClickableSlots}`);
        console.log(`ツールチップ付きスロット: ${slotsWithTooltips}`);

        console.log('✅ 空きスロット判定テスト完了');
    });

    test('予約作成モーダルの動作（空きスロットがある場合）', async ({ page }) => {
        console.log('=== 予約作成モーダルテスト開始 ===');

        // 新宿店を選択
        try {
            await page.click('button:has-text("新宿店")', { timeout: 5000 });
        } catch {
            await page.selectOption('select', { label: '新宿店' });
        }

        await page.waitForTimeout(3000);

        // クリック可能な空きスロットを探す
        const clickableSlots = await page.$$('td.time-cell[wire\\:click]');
        console.log(`クリック可能なスロット数: ${clickableSlots.length}`);

        if (clickableSlots.length > 0) {
            // 最初のクリック可能スロットをクリック
            await clickableSlots[0].click();
            await page.waitForTimeout(2000);

            // モーダルが開いたか確認
            const modal = await page.$('.fixed.inset-0.bg-black');
            if (modal) {
                console.log('✅ 予約作成モーダルが開いた');

                // モーダルのタイトルを確認
                const modalContent = await page.textContent('.bg-white.rounded-lg');
                console.log('モーダル内容（一部）:', modalContent.substring(0, 200));

                // モーダルを閉じる
                await page.click('button:has-text("✕"), button[wire\\:click*="close"]');
                await page.waitForTimeout(1000);
                console.log('モーダルを閉じた');
            }
        } else {
            console.log('⚠️ クリック可能なスロットがない（容量満席の可能性）');
        }

        console.log('✅ 予約作成モーダルテスト完了');
    });

    test('コンソールエラーチェック', async ({ page }) => {
        console.log('=== コンソールエラーチェック開始 ===');

        const consoleErrors = [];
        page.on('console', msg => {
            if (msg.type() === 'error') {
                consoleErrors.push(msg.text());
            }
        });

        // 新宿店を選択
        try {
            await page.click('button:has-text("新宿店")', { timeout: 5000 });
        } catch {
            await page.selectOption('select', { label: '新宿店' });
        }

        await page.waitForTimeout(3000);

        // いくつかの基本的な操作を実行
        const clickableSlots = await page.$$('td.time-cell[wire\\:click]');
        if (clickableSlots.length > 0) {
            await clickableSlots[0].click();
            await page.waitForTimeout(1000);

            // モーダルを閉じる
            const closeButton = await page.$('button:has-text("✕"), button[wire\\:click*="close"]');
            if (closeButton) {
                await closeButton.click();
                await page.waitForTimeout(1000);
            }
        }

        console.log(`コンソールエラー数: ${consoleErrors.length}`);
        if (consoleErrors.length > 0) {
            console.log('エラー:', consoleErrors);
        }

        // 致命的なエラーがないことを確認
        const criticalErrors = consoleErrors.filter(error =>
            !error.includes('favicon') &&
            !error.includes('Warning') &&
            !error.includes('deprecated')
        );

        expect(criticalErrors.length).toBe(0);
        console.log('✅ コンソールエラーチェック完了');
    });
});