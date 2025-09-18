import { test, expect } from '@playwright/test';

test.describe('Admin Edit Modal Test', () => {
  test('should open edit modal in reservation management', async ({ page }) => {
    const baseUrl = 'http://127.0.0.1:8001';
    
    console.log('Starting admin edit modal test...');
    
    // 1. 管理画面にログイン
    await page.goto(`${baseUrl}/admin/login`);
    
    // ログインフォームに入力
    await page.fill('input[type="email"]', 'admin@eye-training.com');
    await page.fill('input[type="password"]', 'password');
    await page.click('button[type="submit"]');
    
    // ダッシュボードが表示されるまで待機
    await page.waitForURL('**/admin');
    console.log('Logged in to admin panel');
    
    // 2. 予約管理ページに移動
    await page.goto(`${baseUrl}/admin/reservations`);
    await page.waitForSelector('table');
    console.log('Navigated to reservations page');
    
    // 3. テーブルを横スクロール
    await page.evaluate(() => {
      const table = document.querySelector('table');
      if (table) {
        table.scrollLeft = table.scrollWidth;
      }
    });
    await page.waitForTimeout(1000);

    // テーブル内のアクションボタンを確認
    const allButtons = page.locator('table button');
    const buttonCount = await allButtons.count();
    console.log(`Found ${buttonCount} total buttons in table`);

    // ボタンのテキストを確認
    for (let i = 0; i < Math.min(5, buttonCount); i++) {
      const btnText = await allButtons.nth(i).textContent();
      console.log(`Button ${i}: ${btnText}`);
    }

    // 編集ボタンを見つける（アイコンボタンの可能性も）
    let editButtons = page.locator('button[title="編集"]');
    let editButtonCount = await editButtons.count();
    console.log(`Found ${editButtonCount} edit buttons with title="編集"`);

    if (editButtonCount === 0) {
      editButtons = page.locator('table button').filter({ has: page.locator('[class*="pencil"]') });
      editButtonCount = await editButtons.count();
      console.log(`Found ${editButtonCount} edit buttons with pencil icon`);
    }

    if (editButtonCount === 0) {
      // テキストで探す
      const textEditButtons = page.locator('button:has-text("編集")');
      const textCount = await textEditButtons.count();
      console.log(`Found ${textCount} edit buttons with text`);

      if (textCount === 0) {
        console.error('No edit buttons found!');
        // スクリーンショットを保存して終了
        await page.screenshot({ path: 'no-edit-buttons.png', fullPage: true });
        return;
      }
      editButtons = textEditButtons;
    }
    
    // 4. 最初の編集ボタンをクリック
    await editButtons.first().click();
    console.log('Clicked edit button');
    
    // 5. モーダルが開くのを待機
    await page.waitForTimeout(2000);
    
    // 6. モーダルの内容を確認
    const modalTitle = page.locator('h2:has-text("予約を編集")');
    const modalVisible = await modalTitle.isVisible();
    console.log(`Modal title visible: ${modalVisible}`);
    
    // 7. フォームフィールドを確認
    const reservationNumberField = page.locator('input[name="reservation_number"]');
    const statusField = page.locator('select[name="status"], [data-test="status-select"]');
    
    console.log('Checking form fields...');
    console.log('Reservation number field exists:', await reservationNumberField.count() > 0);
    console.log('Status field exists:', await statusField.count() > 0);
    
    // 8. モーダル内のすべての入力要素を確認
    const allInputs = page.locator('.fi-modal input, .fi-modal select, .fi-modal textarea');
    const inputCount = await allInputs.count();
    console.log(`Found ${inputCount} input elements in modal`);
    
    // 9. 開いているモーダルを特定
    const openModal = page.locator('.fi-modal-open').first();
    const modalExists = await openModal.count() > 0;
    console.log('Open modal exists:', modalExists);

    if (modalExists) {
      const modalContent = await openModal.innerHTML();
      console.log('Modal HTML length:', modalContent.length);

      // モーダル内のテキストを確認
      const modalText = await openModal.textContent();
      console.log('Modal text preview:', modalText.substring(0, 200));

      // フォーム要素が存在するか確認
      if (modalContent.includes('<form') || modalContent.includes('form')) {
        console.log('Form element found in modal');
      } else {
        console.log('WARNING: No form element found in modal!');
      }
    } else {
      console.log('No open modal found');
    }

    // 10. スクリーンショットを保存
    await page.screenshot({ path: 'edit-modal-test.png', fullPage: true });
    console.log('Screenshot saved as edit-modal-test.png');
  });
});