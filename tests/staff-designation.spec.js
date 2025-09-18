import { test, expect } from '@playwright/test';

test.describe('Staff Designation Flow', () => {
  test('should show staff selection when store has staff assignment enabled and menu requires staff', async ({ page }) => {
    // テスト前の準備：サーバーが8001で動いていることを確認
    const baseUrl = 'http://127.0.0.1:8001';

    console.log('Starting staff designation test...');

    // 1. 店舗選択ページに移動
    await page.goto(`${baseUrl}/stores`);
    await expect(page).toHaveTitle(/店舗一覧|Stores/);

    // 2. 店舗リストが動的に読み込まれるのを待機
    await page.waitForSelector('#stores-container div', { timeout: 10000 });

    // 3. 新宿店の選択ボタンをクリック（use_staff_assignment = 1）
    // 新宿店を含む店舗カードを探して、その中の選択ボタンをクリック
    const shinjukuCard = page.locator('div').filter({ hasText: '新宿店' }).locator('button', { hasText: 'この店舗を選択' }).first();
    await expect(shinjukuCard).toBeVisible();
    await shinjukuCard.click();

    // 4. カテゴリー選択ページが表示されることを確認
    await page.waitForURL('**/reservation/category');
    await expect(page.locator('h1')).toContainText('コースをお選びください');

    // 5. ページのコンテンツをデバッグ確認
    console.log('Page title:', await page.title());
    console.log('Page URL:', page.url());

    // カテゴリー一覧を探す（ケアコースカテゴリーの中にスタンダードコースがある）
    const categoryCards = page.locator('[class*="grid"] button[type="submit"]');
    await page.waitForTimeout(2000); // カテゴリーが読み込まれるのを待つ

    console.log('Categories found:', await categoryCards.count());

    // 各カテゴリーのテキストを確認
    for (let i = 0; i < await categoryCards.count(); i++) {
      const cardText = await categoryCards.nth(i).textContent();
      console.log(`Category ${i}:`, cardText);
    }

    // ケアコースカテゴリーをクリック（スタンダードコースはケアコースカテゴリーに含まれる）
    const careCategory = page.locator('button[type="submit"]').filter({ hasText: 'ケアコース' });
    await expect(careCategory).toBeVisible();
    await careCategory.click();

    // 6. スタッフ選択ページに移動することを確認
    await page.waitForURL('**/reservation/staff');
    await expect(page.locator('h1')).toContainText('担当スタッフをお選びください');

    // 7. ステップインジケーターでスタッフ選択が現在のステップになっていることを確認
    await expect(page.locator('text=ステップ3: スタッフ選択')).toBeVisible();

    // 8. 新宿店店長が選択可能であることを確認
    const staffOption = page.locator('button').filter({ hasText: '新宿店 店長' });
    await expect(staffOption).toBeVisible();

    // 9. スタッフを選択
    await staffOption.click();

    // 10. 時間・料金選択（カレンダー）ページに移動することを確認
    await page.waitForURL('**/reservation/calendar');
    await expect(page.locator('h1')).toContainText(/日時/);

    // 11. 2025-09-30が利用可能な日として表示されることを確認
    const sept30 = page.locator('[data-date*="2025-09-30"]').first();
    if (await sept30.isVisible()) {
      console.log('2025-09-30 is available for booking');

      // 12. 利用可能な時間枠をクリック
      const availableSlot = page.locator('[data-date*="2025-09-30"][data-time]:not(.unavailable)').first();
      if (await availableSlot.isVisible()) {
        await availableSlot.click();

        // 13. 予約フォームが表示され、選択されたスタッフ情報が表示されることを確認
        await expect(page.locator('text=担当スタッフ')).toBeVisible();
        await expect(page.locator('text=新宿店 店長')).toBeVisible();

        console.log('Staff selection flow completed successfully!');
      }
    }
  });

  test('should skip staff selection when menu does not require staff', async ({ page }) => {
    const baseUrl = 'http://127.0.0.1:8001';

    // テスト用にrequires_staff = 0のメニューを使用
    await page.goto(`${baseUrl}/stores`);

    // 新宿店を選択
    await page.click('text=新宿店');

    // requires_staff = 0のメニューを選択（例：他のメニュー）
    const otherMenu = page.locator('text=ケアコースコース60分').first();
    if (await otherMenu.isVisible()) {
      await otherMenu.click();

      // スタッフ選択をスキップして直接時間・料金ページに移動することを確認
      await expect(page.locator('h1')).not.toContainText('担当スタッフをお選びください');
      await expect(page.locator('h1')).toContainText(/時間|料金|日時|カレンダー/);
    }
  });
});