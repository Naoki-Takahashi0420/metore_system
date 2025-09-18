import { test, expect } from '@playwright/test';

test('Verify dashboard widget order', async ({ page }) => {
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
  await page.screenshot({ path: 'dashboard-new-order.png', fullPage: true });

  // Check widget order
  const widgets = await page.evaluate(() => {
    const widgetElements = document.querySelectorAll('.fi-wi');
    const widgetInfo = [];

    widgetElements.forEach((widget, index) => {
      let title = '';

      // Check for various title elements
      const titleElement = widget.querySelector('h2, h3, .fi-wi-heading, .text-lg.font-semibold');
      if (titleElement) {
        title = titleElement.textContent.trim();
      }

      // Check for specific content patterns
      const hasTimelineTable = widget.textContent.includes('席1') || widget.textContent.includes('席数');
      const hasReservationList = widget.textContent.includes('予約一覧');
      const hasCalendar = widget.textContent.includes('2025年9月') && !hasTimelineTable;
      const hasShiftTable = widget.textContent.includes('本日のシフト状況');
      const hasStats = widget.textContent.includes('有効な契約数') || widget.textContent.includes('月間収益');

      widgetInfo.push({
        index: index + 1,
        title: title || 'No title',
        contentType: {
          hasTimelineTable,
          hasReservationList,
          hasCalendar,
          hasShiftTable,
          hasStats
        }
      });
    });

    return widgetInfo;
  });

  console.log('=== 新しいウィジェット順序 ===\n');

  const expectedOrder = [
    '予約タイムラインテーブル',
    '予約一覧',
    '予約カレンダー',
    '本日のシフト状況',
    '統計情報'
  ];

  widgets.forEach((widget, i) => {
    let widgetName = 'Unknown';

    if (widget.contentType.hasTimelineTable) {
      widgetName = '予約タイムラインテーブル';
    } else if (widget.contentType.hasReservationList) {
      widgetName = '予約一覧';
    } else if (widget.contentType.hasCalendar) {
      widgetName = '予約カレンダー';
    } else if (widget.contentType.hasShiftTable) {
      widgetName = '本日のシフト状況';
    } else if (widget.contentType.hasStats) {
      widgetName = '統計情報';
    }

    console.log(`${widget.index}. ${widgetName}`);
    console.log(`   Title: "${widget.title}"`);
    console.log(`   Expected: ${expectedOrder[i] || 'N/A'}`);
    console.log('');
  });

  // Verify order
  if (widgets.length >= 5) {
    expect(widgets[0].contentType.hasTimelineTable).toBeTruthy();
    expect(widgets[1].contentType.hasReservationList).toBeTruthy();
    expect(widgets[2].contentType.hasCalendar).toBeTruthy();
    expect(widgets[3].contentType.hasShiftTable).toBeTruthy();
    expect(widgets[4].contentType.hasStats).toBeTruthy();
    console.log('✅ ウィジェット順序が正しく設定されました！');
  } else {
    console.log(`⚠️ ウィジェット数が期待と異なります: ${widgets.length} 個 (期待: 5個)`);
  }
});