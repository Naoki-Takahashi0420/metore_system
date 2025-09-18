import { test, expect } from '@playwright/test';

test('Check current dashboard configuration', async ({ page }) => {
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
  await page.screenshot({ path: 'current-dashboard.png', fullPage: true });

  // Get all widget information
  const widgetInfo = await page.evaluate(() => {
    const widgets = [];

    // Find all Filament widget containers
    const widgetElements = document.querySelectorAll('.fi-wi');

    widgetElements.forEach((widget, index) => {
      // Get widget heading
      let title = '';
      const headings = widget.querySelectorAll('h2, h3, .text-lg, .text-xl, .font-semibold, .font-bold');
      headings.forEach(h => {
        if (h.textContent && h.textContent.trim().length > 0) {
          title = title || h.textContent.trim();
        }
      });

      // Check for specific content patterns
      const hasTimeAxis = widget.querySelector('[class*="time"]') !== null;
      const hasCalendar = widget.querySelector('[class*="calendar"]') !== null ||
                          widget.querySelector('.fc-daygrid') !== null ||
                          widget.textContent.includes('月') ||
                          widget.textContent.includes('日');
      const hasTable = widget.querySelector('table') !== null;
      const hasChart = widget.querySelector('canvas') !== null;

      // Get widget position
      const rect = widget.getBoundingClientRect();

      widgets.push({
        index: index + 1,
        title: title,
        position: {
          top: Math.round(rect.top),
          left: Math.round(rect.left),
          width: Math.round(rect.width),
          height: Math.round(rect.height)
        },
        contentType: {
          hasTimeAxis,
          hasCalendar,
          hasTable,
          hasChart
        },
        innerHTML: widget.innerHTML.substring(0, 500)
      });
    });

    return widgets.sort((a, b) => a.position.top - b.position.top);
  });

  console.log('=== Current Dashboard Widgets ===\n');
  console.log(`Total widgets found: ${widgetInfo.length}\n`);

  widgetInfo.forEach(widget => {
    console.log(`--- Widget ${widget.index} ---`);
    console.log(`Title: "${widget.title}"`);
    console.log(`Position: top=${widget.position.top}px, height=${widget.position.height}px`);
    console.log(`Content types:`, widget.contentType);

    // Try to identify widget type
    let widgetType = 'Unknown';
    if (widget.title.includes('タイムライン') || widget.contentType.hasTimeAxis) {
      widgetType = 'Timeline Widget';
    } else if (widget.title.includes('予約') && widget.contentType.hasTable) {
      widgetType = 'Reservation List Widget';
    } else if (widget.title.includes('カレンダー') || (widget.contentType.hasCalendar && widget.title.includes('月'))) {
      widgetType = 'Calendar Widget';
    } else if (widget.title.includes('シフト')) {
      widgetType = 'Shift Widget';
    }

    console.log(`Likely type: ${widgetType}`);
    console.log('');
  });

  return widgetInfo;
});