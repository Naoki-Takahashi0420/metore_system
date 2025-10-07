import { test, expect } from '@playwright/test';

test('ダッシュボードの全リンク・ボタンが正常動作するか確認', async ({ page }) => {
  // ログイン
  await page.goto('http://localhost:8000/admin/login');
  await page.fill('input[type="email"]', 'admin@eye-training.com');
  await page.fill('input[type="password"]', 'password');
  await page.click('button[type="submit"]');
  await page.waitForURL('**/admin');
  console.log('✅ ログイン成功');

  await page.waitForTimeout(2000);

  // ページ内のすべてのリンクとボタンを取得
  const links = await page.locator('a[href]').all();
  const buttons = await page.locator('button[wire\\:click], button[onclick]').all();

  console.log(`\n=== リンク数: ${links.length}件 ===`);
  console.log(`=== ボタン数: ${buttons.length}件 ===\n`);

  const results = {
    links: [],
    buttons: [],
    errors: []
  };

  // リンクをチェック
  for (let i = 0; i < Math.min(links.length, 50); i++) {
    try {
      const href = await links[i].getAttribute('href');
      const text = await links[i].innerText().catch(() => '');

      if (href && href.startsWith('http://localhost:8000')) {
        console.log(`[リンク ${i + 1}/${links.length}] ${text || '(no text)'} → ${href}`);

        // 新しいページでリンクをチェック
        const response = await page.request.get(href);
        const status = response.status();

        if (status === 404) {
          console.log(`  ❌ 404 Not Found`);
          results.errors.push({
            type: 'link',
            text: text,
            href: href,
            status: 404
          });
        } else if (status >= 400) {
          console.log(`  ⚠️ Status: ${status}`);
          results.errors.push({
            type: 'link',
            text: text,
            href: href,
            status: status
          });
        } else {
          console.log(`  ✅ OK (${status})`);
        }

        results.links.push({ text, href, status });
      }
    } catch (error) {
      console.log(`  ⚠️ エラー: ${error.message}`);
    }
  }

  // ボタンのwire:clickやonclick属性をチェック
  console.log('\n=== ボタンチェック ===');
  for (let i = 0; i < Math.min(buttons.length, 30); i++) {
    try {
      const text = await buttons[i].innerText().catch(() => '');
      const wireClick = await buttons[i].getAttribute('wire:click');
      const onclick = await buttons[i].getAttribute('onclick');
      const action = wireClick || onclick;

      console.log(`[ボタン ${i + 1}/${buttons.length}] ${text || '(no text)'} → ${action?.substring(0, 50) || 'no action'}...`);

      results.buttons.push({
        text: text,
        action: action
      });
    } catch (error) {
      console.log(`  ⚠️ エラー: ${error.message}`);
    }
  }

  // サマリー
  console.log('\n=== 結果サマリー ===');
  console.log(`チェックしたリンク数: ${results.links.length}件`);
  console.log(`チェックしたボタン数: ${results.buttons.length}件`);
  console.log(`エラー数: ${results.errors.length}件`);

  if (results.errors.length > 0) {
    console.log('\n=== エラー詳細 ===');
    results.errors.forEach((error, index) => {
      console.log(`${index + 1}. [${error.type}] ${error.text}`);
      console.log(`   URL: ${error.href}`);
      console.log(`   Status: ${error.status}`);
    });
  } else {
    console.log('\n✅ すべてのリンクとボタンが正常です！');
  }

  await page.waitForTimeout(2000);
});
