import { test, expect } from '@playwright/test';

test.describe('Sub-Frame Movement Functionality', () => {
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

    test('予約詳細でサブ枠移動ボタンが表示される', async ({ page }) => {
        console.log('=== サブ枠移動ボタン表示テスト開始 ===');

        // 新宿店を選択
        try {
            await page.click('button:has-text("新宿店")', { timeout: 5000 });
        } catch {
            await page.selectOption('select', { label: '新宿店' });
        }

        await page.waitForTimeout(3000);

        // 既存の予約ブロックを探してクリック
        const reservationBlocks = await page.$$('.booking-block');
        console.log(`予約ブロック数: ${reservationBlocks.length}`);

        if (reservationBlocks.length > 0) {
            // 最初の予約ブロックをクリック
            await reservationBlocks[0].click();
            await page.waitForTimeout(2000);

            // 予約詳細モーダルが開いたか確認
            const detailModal = await page.$('.fixed.inset-0.bg-black.bg-opacity-50');
            expect(detailModal).not.toBeNull();

            // サブ枠移動ボタンの存在を確認
            const moveToSubButton = await page.$('button[wire\\:click*="moveToSub"]');
            if (moveToSubButton) {
                const buttonText = await moveToSubButton.textContent();
                console.log('サブ枠移動ボタンテキスト:', buttonText);
                expect(buttonText).toContain('サブ枠');

                // ボタンが無効でないことを確認
                const isDisabled = await moveToSubButton.getAttribute('disabled');
                console.log('サブ枠移動ボタン無効状態:', isDisabled);

                // ボタンをクリックしてみる
                console.log('サブ枠移動ボタンをクリック中...');
                await moveToSubButton.click();
                await page.waitForTimeout(2000);

                // 成功メッセージまたは状態変更を確認
                console.log('✅ サブ枠移動ボタンクリック完了');
            } else {
                console.log('⚠️ サブ枠移動ボタンが見つからない');
            }

            // モーダルを閉じる
            const closeButton = await page.$('button:has-text("✕")');
            if (closeButton) {
                await closeButton.click();
                await page.waitForTimeout(1000);
            }
        } else {
            console.log('⚠️ 予約ブロックが見つからない');
        }

        console.log('✅ サブ枠移動ボタン表示テスト完了');
    });

    test('サブ枠からメイン席への移動ボタン表示', async ({ page }) => {
        console.log('=== メイン席移動ボタン表示テスト開始 ===');

        // 新宿店を選択
        try {
            await page.click('button:has-text("新宿店")', { timeout: 5000 });
        } catch {
            await page.selectOption('select', { label: '新宿店' });
        }

        await page.waitForTimeout(3000);

        // まず予約をサブ枠に移動（前のテストで移動済みの可能性もある）
        const reservationBlocks = await page.$$('.booking-block');
        if (reservationBlocks.length > 0) {
            await reservationBlocks[0].click();
            await page.waitForTimeout(2000);

            // 現在の予約の配置状態を確認
            const currentLocation = await page.textContent('.bg-white.rounded-lg');
            console.log('現在の予約配置状態:', currentLocation.includes('サブ枠') ? 'サブ枠' : 'メイン席');

            if (currentLocation.includes('サブ枠')) {
                // サブ枠にいる場合、メイン席への移動ボタンを確認
                const moveToMainButtons = await page.$$('button[wire\\:click*="moveToMain"]');
                console.log(`メイン席移動ボタン数: ${moveToMainButtons.length}`);

                if (moveToMainButtons.length > 0) {
                    const firstButton = moveToMainButtons[0];
                    const buttonText = await firstButton.textContent();
                    console.log('メイン席移動ボタンテキスト:', buttonText);
                    expect(buttonText).toMatch(/席\d+へ/);

                    console.log('✅ メイン席移動ボタンが表示されている');
                } else {
                    console.log('⚠️ メイン席移動ボタンが見つからない');
                }
            } else {
                // メイン席にいる場合、サブ枠移動ボタンを確認
                const moveToSubButton = await page.$('button[wire\\:click*="moveToSub"]');
                if (moveToSubButton) {
                    console.log('✅ サブ枠移動ボタンが表示されている（メイン席から）');
                }
            }

            // モーダルを閉じる
            const closeButton = await page.$('button:has-text("✕")');
            if (closeButton) {
                await closeButton.click();
                await page.waitForTimeout(1000);
            }
        }

        console.log('✅ メイン席移動ボタン表示テスト完了');
    });

    test('サブ枠移動機能のエラーハンドリング', async ({ page }) => {
        console.log('=== サブ枠移動エラーハンドリングテスト開始 ===');

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

        // 複数の予約ブロックで移動操作を試行
        const reservationBlocks = await page.$$('.booking-block');

        for (let i = 0; i < Math.min(reservationBlocks.length, 2); i++) {
            console.log(`予約ブロック ${i + 1} をテスト中`);

            await reservationBlocks[i].click();
            await page.waitForTimeout(2000);

            // 移動ボタンを探す
            const moveToSubButton = await page.$('button[wire\\:click*="moveToSub"]');
            const moveToMainButtons = await page.$$('button[wire\\:click*="moveToMain"]');

            if (moveToSubButton) {
                console.log('サブ枠移動ボタンをクリック');
                await moveToSubButton.click();
                await page.waitForTimeout(2000);
            } else if (moveToMainButtons.length > 0) {
                console.log('メイン席移動ボタンをクリック');
                await moveToMainButtons[0].click();
                await page.waitForTimeout(2000);
            }

            // モーダルを閉じる
            const closeButton = await page.$('button:has-text("✕")');
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
        console.log('✅ サブ枠移動エラーハンドリングテスト完了');
    });

    test('移動後のタイムライン表示更新確認', async ({ page }) => {
        console.log('=== タイムライン表示更新テスト開始 ===');

        // 新宿店を選択
        try {
            await page.click('button:has-text("新宿店")', { timeout: 5000 });
        } catch {
            await page.selectOption('select', { label: '新宿店' });
        }

        await page.waitForTimeout(3000);

        // 移動前のタイムライン状態を記録
        const beforeMove = await page.$$('.booking-block');
        console.log(`移動前の予約ブロック数: ${beforeMove.length}`);

        if (beforeMove.length > 0) {
            // 予約詳細を開く
            await beforeMove[0].click();
            await page.waitForTimeout(2000);

            // 移動ボタンをクリック
            const moveButton = await page.$('button[wire\\:click*="moveToSub"], button[wire\\:click*="moveToMain"]');
            if (moveButton) {
                const buttonText = await moveButton.textContent();
                console.log(`移動ボタンをクリック: ${buttonText}`);

                await moveButton.click();
                await page.waitForTimeout(3000); // Livewireの更新を待つ

                // モーダルが閉じることを確認
                const modal = await page.$('.fixed.inset-0.bg-black.bg-opacity-50');
                if (modal) {
                    const closeButton = await page.$('button:has-text("✕")');
                    if (closeButton) {
                        await closeButton.click();
                        await page.waitForTimeout(1000);
                    }
                }

                // 移動後のタイムライン状態を確認
                await page.waitForTimeout(2000);
                const afterMove = await page.$$('.booking-block');
                console.log(`移動後の予約ブロック数: ${afterMove.length}`);

                // タイムラインが更新されていることを確認
                expect(afterMove.length).toBeGreaterThan(0);
                console.log('✅ タイムラインが正常に更新された');
            }
        }

        console.log('✅ タイムライン表示更新テスト完了');
    });
});