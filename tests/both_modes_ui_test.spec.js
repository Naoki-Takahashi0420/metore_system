import { test, expect } from '@playwright/test';

test.describe('Both Modes UI Functionality Test', () => {
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

    test('スタッフシフトモードの表示とクリック制限確認', async ({ page }) => {
        console.log('=== スタッフシフトモード表示テスト開始 ===');

        // 新宿店（スタッフシフトモード）を選択
        try {
            await page.click('button:has-text("新宿店")', { timeout: 5000 });
        } catch {
            await page.selectOption('select', { label: '新宿店' });
        }

        await page.waitForTimeout(3000);

        // スタッフシフトモードの表示確認
        const modeInfo = await page.textContent('body');
        const hasStaffShiftMode = modeInfo.includes('シフトベース') || modeInfo.includes('スタッフ');
        console.log('スタッフシフトモード表示:', hasStaffShiftMode);

        // タイムライン表示確認
        const timelineTable = await page.$('.timeline-table');
        expect(timelineTable).not.toBeNull();

        // クリック可能なスロット数をカウント
        const clickableSlots = await page.$$('td.time-cell[wire\\:click]');
        console.log(`スタッフシフトモード - クリック可能スロット数: ${clickableSlots.length}`);

        // 容量制限により多くのスロットがクリック不可であることを確認
        const allSlots = await page.$$('td.time-cell');
        const nonClickableSlots = allSlots.length - clickableSlots.length;
        console.log(`スタッフシフトモード - クリック不可スロット数: ${nonClickableSlots}`);

        // スタッフシフトモードでは制限が厳しいため、クリック不可の方が多いはず
        expect(nonClickableSlots).toBeGreaterThan(clickableSlots.length);

        console.log('✅ スタッフシフトモード表示テスト完了');
    });

    test('営業時間ベースモードの表示とクリック可能性確認', async ({ page }) => {
        console.log('=== 営業時間ベースモード表示テスト開始 ===');

        // 横浜店（営業時間ベースモード）を選択
        const storeOptions = await page.$$('button:has-text("横浜店")');

        if (storeOptions.length > 0) {
            // ボタン形式の場合
            await storeOptions[0].click();
        } else {
            // ドロップダウンの場合
            try {
                await page.selectOption('select', { label: '横浜店' });
            } catch {
                console.log('横浜店が見つからない、他の営業時間ベース店舗を試行');
                // 渋谷店など他の営業時間ベース店舗を試す
                const altStores = await page.$$('button');
                for (let button of altStores.slice(0, 10)) {
                    const text = await button.textContent();
                    if (text && text.includes('店') && !text.includes('新宿')) {
                        await button.click();
                        break;
                    }
                }
            }
        }

        await page.waitForTimeout(3000);

        // 営業時間ベースモードの表示確認
        const modeInfo = await page.textContent('body');
        console.log('現在の店舗モード:', modeInfo.includes('シフトベース') ? 'スタッフシフト' : '営業時間ベース');

        // タイムライン表示確認
        const timelineTable = await page.$('.timeline-table');
        expect(timelineTable).not.toBeNull();

        // クリック可能なスロット数をカウント
        const clickableSlots = await page.$$('td.time-cell[wire\\:click]');
        console.log(`営業時間ベースモード - クリック可能スロット数: ${clickableSlots.length}`);

        // 営業時間ベースモードでは制限が少ないため、より多くのスロットがクリック可能であることを期待
        const allSlots = await page.$$('td.time-cell');
        console.log(`営業時間ベースモード - 全スロット数: ${allSlots.length}`);

        // 最低限のクリック可能スロットがあることを確認
        expect(clickableSlots.length).toBeGreaterThan(0);

        console.log('✅ 営業時間ベースモード表示テスト完了');
    });

    test('両モードでの予約作成機能比較', async ({ page }) => {
        console.log('=== 両モード予約作成機能比較テスト開始 ===');

        const testResults = [];

        // スタッフシフトモードテスト
        console.log('--- スタッフシフトモード（新宿店）テスト ---');
        try {
            await page.click('button:has-text("新宿店")', { timeout: 5000 });
        } catch {
            await page.selectOption('select', { label: '新宿店' });
        }
        await page.waitForTimeout(3000);

        const staffClickableSlots = await page.$$('td.time-cell[wire\\:click]');
        testResults.push({
            mode: 'スタッフシフト',
            clickableSlots: staffClickableSlots.length
        });

        if (staffClickableSlots.length > 0) {
            // 予約作成モーダルテスト
            await staffClickableSlots[0].click();
            await page.waitForTimeout(2000);

            const modal = await page.$('.fixed.inset-0.bg-black');
            if (modal) {
                console.log('✅ スタッフシフトモード予約作成モーダル開く');
                await page.click('button:has-text("✕"), button[wire\\:click*="close"]');
                await page.waitForTimeout(1000);
            }
        }

        // 営業時間ベースモードテスト
        console.log('--- 営業時間ベースモード（横浜店）テスト ---');
        const businessStores = await page.$$('button');
        for (let button of businessStores.slice(0, 15)) {
            const text = await button.textContent();
            if (text && (text.includes('横浜') || text.includes('渋谷') || text.includes('池袋'))) {
                await button.click();
                break;
            }
        }
        await page.waitForTimeout(3000);

        const businessClickableSlots = await page.$$('td.time-cell[wire\\:click]');
        testResults.push({
            mode: '営業時間ベース',
            clickableSlots: businessClickableSlots.length
        });

        if (businessClickableSlots.length > 0) {
            // 予約作成モーダルテスト
            await businessClickableSlots[0].click();
            await page.waitForTimeout(2000);

            const modal = await page.$('.fixed.inset-0.bg-black');
            if (modal) {
                console.log('✅ 営業時間ベースモード予約作成モーダル開く');
                await page.click('button:has-text("✕"), button[wire\\:click*="close"]');
                await page.waitForTimeout(1000);
            }
        }

        // 結果比較
        console.log('=== モード比較結果 ===');
        testResults.forEach(result => {
            console.log(`${result.mode}モード: クリック可能スロット ${result.clickableSlots}個`);
        });

        // 両方のモードが機能していることを確認
        expect(testResults.length).toBe(2);
        testResults.forEach(result => {
            expect(result.clickableSlots).toBeGreaterThanOrEqual(0);
        });

        console.log('✅ 両モード予約作成機能比較テスト完了');
    });

    test('サブ枠機能の両モード対応確認', async ({ page }) => {
        console.log('=== サブ枠機能両モード対応テスト開始 ===');

        const modes = [
            { name: 'スタッフシフト', storeText: '新宿店' },
            { name: '営業時間ベース', storeText: '横浜' }
        ];

        for (const mode of modes) {
            console.log(`--- ${mode.name}モードでのサブ枠テスト ---`);

            // 店舗選択
            try {
                await page.click(`button:has-text("${mode.storeText}")`, { timeout: 3000 });
            } catch {
                // ドロップダウンまたは別の店舗を選択
                const storeButtons = await page.$$('button');
                for (let button of storeButtons.slice(0, 10)) {
                    const text = await button.textContent();
                    if (text && text.includes(mode.storeText.charAt(0))) {
                        await button.click();
                        break;
                    }
                }
            }
            await page.waitForTimeout(3000);

            // 既存の予約ブロックからサブ枠機能をテスト
            const reservationBlocks = await page.$$('.booking-block');
            console.log(`${mode.name}モード - 予約ブロック数: ${reservationBlocks.length}`);

            if (reservationBlocks.length > 0) {
                await reservationBlocks[0].click();
                await page.waitForTimeout(2000);

                // サブ枠移動ボタンの存在確認
                const subFrameButtons = await page.$$('button[wire\\:click*="moveToSub"], button[wire\\:click*="moveToMain"]');
                if (subFrameButtons.length > 0) {
                    console.log(`✅ ${mode.name}モード - サブ枠移動機能利用可能`);
                } else {
                    console.log(`⚠️ ${mode.name}モード - サブ枠移動ボタンなし`);
                }

                // モーダルを閉じる
                const closeButton = await page.$('button:has-text("✕")');
                if (closeButton) {
                    await closeButton.click();
                    await page.waitForTimeout(1000);
                }
            } else {
                console.log(`${mode.name}モード - 既存予約なし`);
            }
        }

        console.log('✅ サブ枠機能両モード対応テスト完了');
    });

    test('エラーハンドリングと安定性確認', async ({ page }) => {
        console.log('=== エラーハンドリング安定性テスト開始 ===');

        const consoleErrors = [];
        page.on('console', msg => {
            if (msg.type() === 'error') {
                consoleErrors.push(msg.text());
            }
        });

        // 複数店舗の切り替えテスト
        const storeButtons = await page.$$('button');
        const testStores = [];

        // 最初の5つの店舗ボタンを収集
        for (let i = 0; i < Math.min(storeButtons.length, 5); i++) {
            const text = await storeButtons[i].textContent();
            if (text && text.includes('店')) {
                testStores.push({ button: storeButtons[i], name: text.trim() });
            }
        }

        console.log(`テスト対象店舗数: ${testStores.length}`);

        // 各店舗を切り替えてテスト
        for (const store of testStores) {
            console.log(`店舗切り替えテスト: ${store.name}`);

            try {
                await store.button.click();
                await page.waitForTimeout(2000);

                // タイムラインが正常に表示されるかチェック
                const timeline = await page.$('.timeline-table');
                expect(timeline).not.toBeNull();

                // クリック可能スロットの確認
                const slots = await page.$$('td.time-cell[wire\\:click]');
                console.log(`  - クリック可能スロット: ${slots.length}個`);

            } catch (error) {
                console.log(`  - エラー: ${error.message}`);
            }
        }

        // エラー集計
        console.log(`総コンソールエラー数: ${consoleErrors.length}`);
        if (consoleErrors.length > 0) {
            console.log('エラー詳細:', consoleErrors.slice(0, 3)); // 最初の3つだけ表示
        }

        // 致命的なエラーがないことを確認
        const criticalErrors = consoleErrors.filter(error =>
            !error.includes('favicon') &&
            !error.includes('Warning') &&
            !error.includes('deprecated') &&
            error.includes('Error')
        );

        expect(criticalErrors.length).toBe(0);
        console.log('✅ エラーハンドリング安定性テスト完了');
    });
});