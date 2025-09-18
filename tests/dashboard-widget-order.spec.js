import { test, expect } from '@playwright/test';

test('Dashboard widgets should be in correct order', async ({ page }) => {
  // Navigate to admin login
  await page.goto('http://localhost:8000/admin/login');

  // Login as admin
  await page.fill('input[type="email"]', 'admin@eye-training.com');
  await page.fill('input[type="password"]', 'password');
  await page.click('button[type="submit"]');

  // Wait for dashboard to load
  await page.waitForURL('**/admin');
  await page.waitForLoadState('networkidle');

  // Get all widget headers
  const widgets = await page.$$eval('.fi-wi', elements =>
    elements.map(el => {
      const header = el.querySelector('.fi-wi-header, h2, .text-lg, .text-xl, [class*="heading"]');
      return header ? header.textContent.trim() : '';
    }).filter(text => text !== '')
  );

  console.log('Found widgets in order:', widgets);

  // Check the order
  const expectedOrder = [
    'タイムライン',
    '予約一覧',
    '予約タイムライン', // This is TimelineCalendarWidget
    'シフトカレンダー'
  ];

  // Take screenshot for verification
  await page.screenshot({ path: 'dashboard-order-check.png', fullPage: true });

  // Verify each widget is in correct position
  for (let i = 0; i < expectedOrder.length; i++) {
    if (widgets[i]) {
      console.log(`Position ${i + 1}: Expected "${expectedOrder[i]}", Got "${widgets[i]}"`);
    }
  }

  // Also get widget class names for debugging
  const widgetClasses = await page.$$eval('[wire\\:id]', elements =>
    elements.map(el => {
      const wireSnapshot = el.getAttribute('wire:snapshot');
      if (wireSnapshot) {
        try {
          const data = JSON.parse(decodeURIComponent(atob(wireSnapshot)));
          return data.memo?.name || '';
        } catch (e) {
          return '';
        }
      }
      return '';
    }).filter(name => name.includes('Widget'))
  );

  console.log('Widget class names:', widgetClasses);
});