import { test, expect } from '@playwright/test';

test('Investigate timeline structure for current time indicator', async ({ page }) => {
    // ログイン
    await page.goto('http://localhost:8000/admin/login');
    await page.fill('input[type="email"]', 'admin@eye-training.com');
    await page.fill('input[type="password"]', 'password');
    await page.click('button[type="submit"]');
    await page.waitForURL('**/admin');

    // ダッシュボードが読み込まれるまで待つ
    await page.waitForSelector('.timeline-table', { timeout: 10000 });

    // タイムラインの構造を調査
    const investigation = await page.evaluate(() => {
        const result = {
            hasTimelineTable: false,
            tableStructure: {},
            timeSlots: [],
            containerInfo: {},
            currentTimeIndicator: null,
            errors: []
        };

        // タイムラインテーブルの存在確認
        const timelineTable = document.querySelector('.timeline-table');
        if (timelineTable) {
            result.hasTimelineTable = true;

            // テーブルの構造を取得
            result.tableStructure = {
                offsetTop: timelineTable.offsetTop,
                offsetLeft: timelineTable.offsetLeft,
                offsetWidth: timelineTable.offsetWidth,
                offsetHeight: timelineTable.offsetHeight,
                position: window.getComputedStyle(timelineTable).position
            };

            // ヘッダー行の情報
            const headerRow = timelineTable.querySelector('thead tr');
            if (headerRow) {
                const headerCells = headerRow.querySelectorAll('th');
                result.tableStructure.headerHeight = headerRow.offsetHeight;
                result.tableStructure.numberOfColumns = headerCells.length;

                // 最初のセルの幅（席数/スタッフ列）
                if (headerCells[0]) {
                    result.tableStructure.firstColumnWidth = headerCells[0].offsetWidth;
                }

                // 時間スロットのヘッダーを解析
                Array.from(headerCells).slice(1).forEach((cell, index) => {
                    result.timeSlots.push({
                        index: index,
                        text: cell.textContent.trim(),
                        offsetLeft: cell.offsetLeft,
                        offsetWidth: cell.offsetWidth,
                        colspan: cell.getAttribute('colspan') || '1'
                    });
                });
            }

            // データ行の最初の行を取得（スロットの実際の幅を計算）
            const firstDataRow = timelineTable.querySelector('tbody tr');
            if (firstDataRow) {
                const dataCells = firstDataRow.querySelectorAll('td');
                result.tableStructure.dataRowHeight = firstDataRow.offsetHeight;
                result.tableStructure.actualSlots = [];

                Array.from(dataCells).slice(1).forEach((cell, index) => {
                    result.tableStructure.actualSlots.push({
                        index: index,
                        offsetLeft: cell.offsetLeft,
                        offsetWidth: cell.offsetWidth,
                        className: cell.className
                    });
                });
            }
        }

        // コンテナの情報を取得
        const container = document.querySelector('.overflow-x-auto');
        if (container) {
            result.containerInfo = {
                scrollLeft: container.scrollLeft,
                scrollWidth: container.scrollWidth,
                clientWidth: container.clientWidth,
                position: window.getComputedStyle(container).position,
                overflow: window.getComputedStyle(container).overflow
            };
        }

        // 現在時刻インジケーターの確認
        const indicator = document.getElementById('current-time-indicator');
        if (indicator) {
            result.currentTimeIndicator = {
                exists: true,
                display: window.getComputedStyle(indicator).display,
                position: window.getComputedStyle(indicator).position,
                left: window.getComputedStyle(indicator).left,
                top: window.getComputedStyle(indicator).top,
                width: window.getComputedStyle(indicator).width,
                height: window.getComputedStyle(indicator).height,
                zIndex: window.getComputedStyle(indicator).zIndex
            };
        } else {
            result.currentTimeIndicator = { exists: false };
        }

        // 現在の時刻
        const now = new Date();
        result.currentTime = {
            hours: now.getHours(),
            minutes: now.getMinutes(),
            formatted: now.toTimeString().slice(0, 5)
        };

        return result;
    });

    console.log('\n========== タイムライン構造調査結果 ==========\n');
    console.log('📊 テーブル構造:');
    console.log(JSON.stringify(investigation.tableStructure, null, 2));

    console.log('\n⏰ 時間スロット情報:');
    console.log(`  スロット数: ${investigation.timeSlots.length}`);
    if (investigation.timeSlots.length > 0) {
        console.log(`  最初のスロット: ${investigation.timeSlots[0].text}`);
        console.log(`  最後のスロット: ${investigation.timeSlots[investigation.timeSlots.length - 1].text}`);
    }

    console.log('\n📦 コンテナ情報:');
    console.log(JSON.stringify(investigation.containerInfo, null, 2));

    console.log('\n🔴 現在時刻インジケーター:');
    console.log(JSON.stringify(investigation.currentTimeIndicator, null, 2));

    console.log('\n⏱ 現在時刻:', investigation.currentTime.formatted);

    // 計算例：現在時刻の位置
    if (investigation.tableStructure.actualSlots && investigation.tableStructure.actualSlots.length > 0) {
        const currentHour = investigation.currentTime.hours;
        const currentMinute = investigation.currentTime.minutes;

        // 10:00-20:00の営業時間と仮定（15分刻み）
        const startHour = 10;
        const endHour = 20;
        const slotDuration = 15; // 分

        if (currentHour >= startHour && currentHour < endHour) {
            const totalMinutesFromStart = (currentHour - startHour) * 60 + currentMinute;
            const slotIndex = Math.floor(totalMinutesFromStart / slotDuration);
            const positionInSlot = (totalMinutesFromStart % slotDuration) / slotDuration;

            console.log('\n📍 位置計算:');
            console.log(`  営業開始からの経過時間: ${totalMinutesFromStart}分`);
            console.log(`  スロットインデックス: ${slotIndex}`);
            console.log(`  スロット内の位置: ${(positionInSlot * 100).toFixed(1)}%`);

            if (investigation.tableStructure.actualSlots[slotIndex]) {
                const slot = investigation.tableStructure.actualSlots[slotIndex];
                const calculatedLeft = slot.offsetLeft + (slot.offsetWidth * positionInSlot);
                console.log(`  計算された左位置: ${calculatedLeft}px`);
            }
        } else {
            console.log('\n⚠️ 現在時刻は営業時間外です');
        }
    }

    // スクリーンショットを撮る
    await page.screenshot({ path: 'timeline-structure.png', fullPage: true });
    console.log('\n📸 スクリーンショット: timeline-structure.png');
});