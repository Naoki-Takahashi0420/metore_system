import { test, expect } from '@playwright/test';

test('Analyze dashboard in detail', async ({ page }) => {
  // Navigate to admin login
  await page.goto('http://localhost:8000/admin/login');

  // Login as admin
  await page.fill('input[type="email"]', 'admin@eye-training.com');
  await page.fill('input[type="password"]', 'password');
  await page.click('button[type="submit"]');

  // Wait for dashboard to load
  await page.waitForURL('**/admin');
  await page.waitForLoadState('networkidle');

  // Take screenshot
  await page.screenshot({ path: 'dashboard-detail.png', fullPage: true });

  // Analyze each section
  console.log('=== ダッシュボード詳細分析 ===\n');

  // 1. Top section analysis (Timeline)
  const topSection = await page.evaluate(() => {
    const timeline = document.querySelector('.fi-wi');
    if (!timeline) return null;

    // Find timeline specific elements
    const timeHeaders = timeline.querySelectorAll('[class*="10:00"], [class*="11:00"], [class*="12:00"]');
    const hasTimeAxis = timeline.textContent.includes('10:00') || timeline.textContent.includes('11:00');
    const reservationBar = timeline.querySelector('[style*="background"]');

    return {
      hasTimeAxis,
      timeAxisContent: hasTimeAxis ? 'Found time axis (10:00-17:00)' : 'No time axis',
      hasReservationBar: reservationBar !== null,
      content: timeline.textContent.substring(0, 200)
    };
  });

  console.log('1. 上部セクション（タイムライン部分）:');
  console.log(topSection);
  console.log('');

  // 2. Calendar section analysis
  const calendarSection = await page.evaluate(() => {
    const calendar = document.querySelector('[class*="2025年9月"]');
    if (!calendar) return null;

    const parent = calendar.closest('.fi-wi') || calendar.closest('[wire\\:id]');
    const hasMonthDisplay = parent.textContent.includes('2025年9月');
    const hasDayNumbers = parent.textContent.includes('18日') || parent.querySelector('[class*="18"]');

    return {
      hasMonthDisplay,
      hasDayNumbers: hasDayNumbers !== null,
      monthText: '2025年9月',
      content: parent.textContent.substring(200, 400)
    };
  });

  console.log('2. カレンダーセクション:');
  console.log(calendarSection);
  console.log('');

  // 3. Stats section analysis
  const statsSection = await page.evaluate(() => {
    const stats = document.querySelector('[class*="有効な契約数"]');
    if (!stats) return null;

    const parent = stats.closest('div');
    const hasActiveCount = parent.textContent.includes('有効な契約数');
    const hasRevenue = parent.textContent.includes('¥102,000') || parent.textContent.includes('月間収益');

    return {
      hasActiveCount,
      hasRevenue,
      activeContracts: '8',
      revenue: '¥102,000',
      content: parent.textContent
    };
  });

  console.log('3. 統計セクション:');
  console.log(statsSection);
  console.log('');

  // 4. Bottom table section
  const tableSection = await page.evaluate(() => {
    const table = document.querySelector('table');
    if (!table) return null;

    // Check table headers
    const headers = [];
    table.querySelectorAll('th').forEach(th => {
      headers.push(th.textContent.trim());
    });

    // Check first row data
    const firstRow = table.querySelector('tbody tr');
    const rowData = [];
    if (firstRow) {
      firstRow.querySelectorAll('td').forEach(td => {
        rowData.push(td.textContent.trim());
      });
    }

    // Look for title above table
    const tableTitle = document.querySelector('[class*="予約一覧"], [class*="今日の"], [class*="シフト"]');

    return {
      title: tableTitle ? tableTitle.textContent.trim() : 'No title found',
      headers,
      firstRowData: rowData,
      rowCount: table.querySelectorAll('tbody tr').length
    };
  });

  console.log('4. 最下部テーブルセクション:');
  console.log(tableSection);
  console.log('');

  // Check for specific text
  const pageText = await page.evaluate(() => document.body.textContent);

  console.log('5. ページ内のキーワード:');
  console.log('- "予約一覧" found:', pageText.includes('予約一覧'));
  console.log('- "今日" found:', pageText.includes('今日'));
  console.log('- "シフト" found:', pageText.includes('シフト'));
  console.log('- "田中 うんこたれ" found:', pageText.includes('田中 うんこたれ'));
  console.log('- "スタンダードコース" found:', pageText.includes('スタンダードコース'));
});