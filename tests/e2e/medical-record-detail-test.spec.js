import { test, expect } from '@playwright/test';

test('カルテ詳細ページのデータ表示確認', async ({ page }) => {
  // ログイン
  await page.goto('http://localhost:8000/admin/login');
  await page.fill('input[type="email"]', 'admin@eye-training.com');
  await page.fill('input[type="password"]', 'password');
  await page.click('button[type="submit"]');
  await page.waitForURL('**/admin');
  console.log('✅ ログイン成功');

  // カルテ詳細ページに直接アクセス
  await page.goto('http://localhost:8000/admin/medical-records/75');
  await page.waitForTimeout(2000);
  console.log('✅ カルテ詳細ページにアクセス');

  // ページの全HTMLを取得してログ出力
  const bodyHTML = await page.locator('body').innerHTML();
  console.log('\n=== ページHTML (抜粋) ===');
  console.log(bodyHTML.substring(0, 5000));

  // カルテ履歴タイムラインのデータを確認
  console.log('\n=== カルテ履歴タイムライン ===');
  const timelineSection = page.locator('section').filter({ hasText: 'カルテ履歴タイムライン' });
  const timelineSectionExists = await timelineSection.count() > 0;
  console.log('タイムラインセクション存在:', timelineSectionExists);

  if (timelineSectionExists) {
    const timelineHTML = await timelineSection.innerHTML();
    console.log('タイムラインHTML (最初の2000文字):', timelineHTML.substring(0, 2000));

    // 施術日の値を確認
    const treatmentDates = await timelineSection.locator('dd').filter({ hasText: /^\d{4}\/\d{2}\/\d{2}/ }).allTextContents();
    console.log('施術日の値:', treatmentDates);

    // 店舗の値を確認
    const stores = await timelineSection.locator('dt:has-text("店舗") + dd').allTextContents();
    console.log('店舗の値:', stores);

    // 担当者の値を確認
    const handlers = await timelineSection.locator('dt:has-text("担当者") + dd').allTextContents();
    console.log('担当者の値:', handlers);

    // 主訴の値を確認
    const complaints = await timelineSection.locator('dt:has-text("主訴") + dd').allTextContents();
    console.log('主訴の値:', complaints);
  }

  // 顧客画像セクションを確認
  console.log('\n=== 顧客画像セクション ===');
  const imageSection = page.locator('section').filter({ hasText: '顧客画像' });
  const imageSectionExists = await imageSection.count() > 0;
  console.log('顧客画像セクション存在:', imageSectionExists);

  if (imageSectionExists) {
    const imageSectionHTML = await imageSection.innerHTML();
    console.log('顧客画像HTML (最初の1000文字):', imageSectionHTML.substring(0, 1000));

    // 画像の数を確認
    const images = await imageSection.locator('img').count();
    console.log('画像の数:', images);

    // タイトルの値を確認
    const titles = await imageSection.locator('dt:has-text("タイトル") + dd').allTextContents();
    console.log('タイトルの値:', titles);

    // 種類の値を確認
    const types = await imageSection.locator('dt:has-text("種類") + dd').allTextContents();
    console.log('種類の値:', types);
  }

  // カルテ情報タブを確認
  console.log('\n=== カルテ情報タブ ===');
  const tabsSection = page.locator('[role="tablist"]');
  const tabsExists = await tabsSection.count() > 0;
  console.log('タブセクション存在:', tabsExists);

  if (tabsExists) {
    // 各タブをクリックして内容を確認
    const tabs = ['基本情報', '顧客管理情報', '視力記録', '接客メモ・引き継ぎ'];

    for (const tabName of tabs) {
      console.log(`\n--- ${tabName}タブ ---`);
      const tab = page.locator(`button[role="tab"]:has-text("${tabName}")`);
      const tabExists = await tab.count() > 0;
      console.log(`${tabName}タブ存在:`, tabExists);

      if (tabExists) {
        await tab.click();
        await page.waitForTimeout(500);

        // タブパネルのHTMLを取得
        const tabPanel = page.locator('[role="tabpanel"]').first();
        const tabPanelHTML = await tabPanel.innerHTML();
        console.log(`${tabName}タブの内容 (最初の1000文字):`, tabPanelHTML.substring(0, 1000));
      }
    }
  }

  // データベースから実際のデータを確認
  console.log('\n=== データベースクエリ実行 ===');
  await page.goto('http://localhost:8000/admin');

  // ブラウザコンソールでデータを取得
  const medicalRecordData = await page.evaluate(async () => {
    try {
      const response = await fetch('/admin/medical-records/75');
      const html = await response.text();
      return {
        success: true,
        htmlLength: html.length,
        hasData: html.includes('主訴') || html.includes('症状')
      };
    } catch (error) {
      return { success: false, error: error.message };
    }
  });
  console.log('フェッチ結果:', medicalRecordData);

  await page.waitForTimeout(5000);
});
