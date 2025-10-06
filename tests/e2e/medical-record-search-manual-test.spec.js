import { test, expect } from '@playwright/test';

test('カルテ管理で具体的なフルネーム検索テスト', async ({ page }) => {
  console.log('🔧 テスト開始: 具体的なフルネーム検索');

  // 1. 管理者アカウントでログイン
  await page.goto('http://localhost:8001/admin/login');
  await page.fill('input[type="email"]', 'admin@eye-training.com');
  await page.fill('input[type="password"]', 'password');
  await page.click('button[type="submit"]');
  await page.waitForURL('**/admin');
  console.log('✅ ログイン成功');

  // 2. カルテ管理ページにアクセス
  await page.goto('http://localhost:8001/admin/medical-records');
  await page.waitForLoadState('networkidle');
  console.log('✅ カルテ管理ページにアクセス');

  // 3. 検索ボックスを探す
  const searchInput = page.locator('input[placeholder*="検索"]').or(page.locator('input[type="search"]')).first();

  // 4. フルネームで検索（スペースあり）
  console.log('\n📝 テスト1: フルネーム（スペースあり）で検索');
  await searchInput.fill('岩下 仁');
  await page.waitForTimeout(1500);

  let rowCount = await page.locator('tbody tr').count();
  console.log(`   検索語: "岩下 仁" → ${rowCount}件`);
  await page.screenshot({ path: 'search-test-fullname-with-space.png', fullPage: true });

  // 5. フルネームで検索（スペースなし）
  console.log('\n📝 テスト2: フルネーム（スペースなし）で検索');
  await searchInput.clear();
  await searchInput.fill('岩下仁');
  await page.waitForTimeout(1500);

  rowCount = await page.locator('tbody tr').count();
  console.log(`   検索語: "岩下仁" → ${rowCount}件`);
  await page.screenshot({ path: 'search-test-fullname-no-space.png', fullPage: true });

  // 6. 姓のみで検索
  console.log('\n📝 テスト3: 姓のみで検索');
  await searchInput.clear();
  await searchInput.fill('岩下');
  await page.waitForTimeout(1500);

  rowCount = await page.locator('tbody tr').count();
  console.log(`   検索語: "岩下" → ${rowCount}件`);
  await page.screenshot({ path: 'search-test-lastname.png', fullPage: true });

  // 7. 名のみで検索
  console.log('\n📝 テスト4: 名のみで検索');
  await searchInput.clear();
  await searchInput.fill('仁');
  await page.waitForTimeout(1500);

  rowCount = await page.locator('tbody tr').count();
  console.log(`   検索語: "仁" → ${rowCount}件`);
  await page.screenshot({ path: 'search-test-firstname.png', fullPage: true });

  // 8. 別の顧客でテスト
  console.log('\n📝 テスト5: 別の顧客（戸塚 貴子）で検索');
  await searchInput.clear();
  await searchInput.fill('戸塚 貴子');
  await page.waitForTimeout(1500);

  rowCount = await page.locator('tbody tr').count();
  console.log(`   検索語: "戸塚 貴子" → ${rowCount}件`);
  await page.screenshot({ path: 'search-test-tozuka.png', fullPage: true });

  console.log('\n✅ 全ての検索パターンが動作しました');
});
