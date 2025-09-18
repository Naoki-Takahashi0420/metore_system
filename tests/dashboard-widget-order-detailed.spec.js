import { test, expect } from '@playwright/test';

test('Dashboard widgets order check', async ({ page }) => {
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
  await page.screenshot({ path: 'dashboard-full.png', fullPage: true });

  // Get all widget containers with more specific selectors
  const widgetInfo = await page.evaluate(() => {
    const widgets = [];

    // Find all Filament widget containers
    const widgetElements = document.querySelectorAll('.fi-wi');

    widgetElements.forEach((widget, index) => {
      // Look for various heading elements
      let title = '';
      const heading = widget.querySelector('h2, h3, .fi-wi-header h2, .text-lg.font-semibold, .text-xl');
      if (heading) {
        title = heading.textContent.trim();
      }

      // Also check for specific widget classes
      const wireId = widget.querySelector('[wire\\:id]');
      let componentName = '';
      if (wireId) {
        const wireSnapshot = wireId.getAttribute('wire:snapshot');
        if (wireSnapshot) {
          try {
            const data = JSON.parse(decodeURIComponent(atob(wireSnapshot)));
            componentName = data.memo?.name || '';
          } catch (e) {}
        }
      }

      // Get the widget's position on page
      const rect = widget.getBoundingClientRect();

      widgets.push({
        index: index + 1,
        title: title,
        component: componentName,
        top: rect.top,
        height: rect.height,
        html: widget.innerHTML.substring(0, 200) // First 200 chars for debugging
      });
    });

    return widgets.sort((a, b) => a.top - b.top);
  });

  console.log('=== Dashboard Widget Order ===');
  widgetInfo.forEach(w => {
    console.log(`Position ${w.index}: "${w.title}" (Component: ${w.component})`);
    console.log(`  Top: ${w.top}px, Height: ${w.height}px`);
    if (w.title === '') {
      console.log(`  HTML preview: ${w.html.substring(0, 100)}...`);
    }
  });

  // Expected order
  const expectedOrder = [
    { contains: 'タイムライン', component: 'reservation-timeline' },
    { contains: '予約一覧', component: 'today-reservations' },
    { contains: 'カレンダー', component: 'timeline-calendar' },
    { contains: 'シフト', component: 'shift-calendar' }
  ];

  console.log('\n=== Expected vs Actual ===');
  expectedOrder.forEach((expected, i) => {
    const actual = widgetInfo[i];
    if (actual) {
      console.log(`${i + 1}. Expected: ${expected.contains || expected.component}`);
      console.log(`   Actual: "${actual.title}" (${actual.component})`);
    } else {
      console.log(`${i + 1}. Expected: ${expected.contains || expected.component}`);
      console.log(`   Actual: NOT FOUND`);
    }
  });

  // Return the widget titles for verification
  return widgetInfo.map(w => w.title || w.component);
});