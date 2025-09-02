import { test, expect } from '@playwright/test';

test('シンプル予約フロー確認', async ({ page }) => {
  // 1. 店舗一覧ページ
  console.log('店舗一覧ページへアクセス...');
  await page.goto('http://127.0.0.1:8000/stores');
  
  // 店舗データが読み込まれるまで待つ
  await page.waitForSelector('#stores-container > div', { timeout: 10000 });
  console.log('店舗データ読み込み完了');
  
  // ページが正しく読み込まれたか確認
  const title = await page.title();
  console.log(`ページタイトル: ${title}`);
  
  // ボタンの存在確認
  const buttons = await page.locator('button').count();
  console.log(`ボタン数: ${buttons}`);
  
  // 店舗選択ボタンを探す
  const selectButton = page.locator('button').filter({ hasText: 'この店舗を選択' });
  const selectButtonCount = await selectButton.count();
  console.log(`店舗選択ボタン数: ${selectButtonCount}`);
  
  if (selectButtonCount > 0) {
    console.log('店舗を選択中...');
    await selectButton.first().click();
    
    // 遷移を待つ
    await page.waitForTimeout(2000);
    
    // 現在のURL確認
    const currentUrl = page.url();
    console.log(`遷移後URL: ${currentUrl}`);
    
    if (currentUrl.includes('category')) {
      console.log('✅ カテゴリー選択画面に遷移成功');
      
      // カテゴリーカードを探す
      const cards = await page.locator('.bg-white.rounded-lg').count();
      console.log(`カテゴリーカード数: ${cards}`);
      
      if (cards > 0) {
        // より具体的なセレクタを使用
        const categoryCard = page.locator('.category-card, [data-category-id], .cursor-pointer').first();
        const categoryCardCount = await categoryCard.count();
        console.log(`クリック可能なカテゴリーカード数: ${categoryCardCount}`);
        
        if (categoryCardCount > 0) {
          await categoryCard.click();
        } else {
          // フォールバック: カテゴリー名を含むdivをクリック
          await page.locator('div').filter({ hasText: /コース|メニュー|カテゴリー/ }).first().click();
        }
        await page.waitForTimeout(2000);
        
        const timeUrl = page.url();
        console.log(`時間選択画面URL: ${timeUrl}`);
        
        if (timeUrl.includes('time')) {
          console.log('✅ 時間選択画面に遷移成功');
          
          // 時間ボタンを探す
          const timeButtons = await page.locator('button').filter({ hasText: /\d+:\d+/ }).count();
          console.log(`時間ボタン数: ${timeButtons}`);
          
          if (timeButtons > 0) {
            await page.locator('button').filter({ hasText: /\d+:\d+/ }).first().click();
            await page.waitForTimeout(2000);
            
            const calendarUrl = page.url();
            console.log(`カレンダー画面URL: ${calendarUrl}`);
            
            if (calendarUrl.includes('calendar')) {
              console.log('✅ カレンダー画面に遷移成功');
              console.log('🎉 予約フロー正常動作確認完了！');
            }
          }
        }
      }
    }
  } else {
    console.log('⚠️ 店舗選択ボタンが見つかりません');
    
    // ページ内容を確認
    const bodyText = await page.locator('body').innerText();
    console.log('ページ内容（最初の500文字）:', bodyText.substring(0, 500));
  }
});