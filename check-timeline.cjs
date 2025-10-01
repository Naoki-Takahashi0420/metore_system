const { chromium } = require('playwright');

(async () => {
    const browser = await chromium.launch({ headless: false });
    const page = await browser.newPage();

    try {
        // ダッシュボードにアクセス
        console.log('📍 ダッシュボードにアクセス中...');
        await page.goto('http://localhost:8000/admin');

        // ログインが必要な場合
        if (page.url().includes('/login')) {
            console.log('🔑 ログイン中...');
            await page.fill('input[name="email"]', 'admin@eye-training.com');
            await page.fill('input[name="password"]', 'password');
            await page.click('button[type="submit"]');
            await page.waitForTimeout(3000);
        }

        console.log('⏳ ページ読み込み待機中...');
        await page.waitForTimeout(5000);

        // インジケーター確認
        const indicator = await page.locator('#current-time-indicator');
        const exists = await indicator.count() > 0;

        console.log('\n=== 🔍 インジケーター確認 ===');
        console.log('存在:', exists ? '✅' : '❌');

        if (exists) {
            const startHour = await indicator.getAttribute('data-timeline-start');
            const endHour = await indicator.getAttribute('data-timeline-end');
            const slotDuration = await indicator.getAttribute('data-slot-duration');
            const leftPosition = await indicator.evaluate(el => el.style.left);

            console.log('\n=== 📊 属性情報 ===');
            console.log('開始時刻:', startHour);
            console.log('終了時刻:', endHour);
            console.log('スロット:', slotDuration);
            console.log('left位置:', leftPosition);

            // 現在時刻を取得
            const currentTime = await page.evaluate(() => {
                const now = new Date().toLocaleString("en-US", {timeZone: "Asia/Tokyo"});
                const jstDate = new Date(now);
                return {
                    hour: jstDate.getHours(),
                    minute: jstDate.getMinutes()
                };
            });

            console.log('\n=== ⏰ 時刻情報 ===');
            console.log('現在時刻:', `${currentTime.hour}:${String(currentTime.minute).padStart(2, '0')}`);

            // 計算検証
            const minutesFromStart = (currentTime.hour - parseInt(startHour)) * 60 + currentTime.minute;
            const cellIndex = Math.floor(minutesFromStart / parseInt(slotDuration));
            const percentageIntoCell = (minutesFromStart % parseInt(slotDuration)) / parseInt(slotDuration);
            const expectedLeft = 36 + (cellIndex * 48) + (percentageIntoCell * 48);

            console.log('\n=== 🧮 計算検証 ===');
            console.log('開始からの分数:', minutesFromStart);
            console.log('セルインデックス:', cellIndex);
            console.log('期待される位置:', expectedLeft.toFixed(1) + 'px');
            console.log('実際の位置:', leftPosition);
            console.log('差分:', Math.abs(expectedLeft - parseFloat(leftPosition)).toFixed(1) + 'px');
        }

        // スクリーンショット撮影
        await page.screenshot({ path: 'timeline-debug.png', fullPage: true });
        console.log('\n📸 スクリーンショット保存: timeline-debug.png');

    } catch (error) {
        console.error('❌ エラー:', error.message);
    } finally {
        await browser.close();
    }
})();
