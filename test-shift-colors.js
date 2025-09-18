import { chromium } from '@playwright/test';

(async () => {
    const browser = await chromium.launch({ headless: false });
    const page = await browser.newPage();

    try {
        // ログイン
        await page.goto('http://localhost:8000/admin/login');
        await page.fill('input[type="email"]', 'admin@eye-training.com');
        await page.fill('input[type="password"]', 'password');
        await page.click('button[type="submit"]');

        // ダッシュボードに移動
        await page.waitForURL('**/admin', { timeout: 10000 });
        console.log('✅ ダッシュボードに移動');

        // シフト管理ページに移動
        await page.goto('http://localhost:8000/admin/simple-shift-management');
        await page.waitForTimeout(3000);
        console.log('✅ シフト管理ページに移動');

        // スタッフ凡例を確認
        const staffLegend = await page.$('.bg-white:has-text("スタッフ一覧")');
        if (staffLegend) {
            console.log('✅ スタッフ凡例が表示されています');

            // 凡例の数を確認
            const legendItems = await page.$$('.bg-white:has-text("スタッフ一覧") .flex.items-center.gap-1');
            console.log(`   凡例のスタッフ数: ${legendItems.length}`);
        }

        // カレンダーのシフトを確認
        const shifts = await page.$$('[data-shift-id]');
        console.log(`\n📅 カレンダー内のシフト数: ${shifts.length}`);

        if (shifts.length > 0) {
            // 最初のシフトの色を確認
            const firstShift = shifts[0];
            const style = await firstShift.getAttribute('style');
            console.log('   最初のシフトのスタイル:', style);
        }

        // スクリーンショット保存
        await page.screenshot({ path: 'shift-colors.png', fullPage: true });
        console.log('\n📸 スクリーンショット保存: shift-colors.png');

    } catch (error) {
        console.error('❌ エラー:', error.message);
    }

    await page.waitForTimeout(5000);
    await browser.close();
})();