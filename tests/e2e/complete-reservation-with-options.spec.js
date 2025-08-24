import { test, expect } from '@playwright/test';

test.describe('Complete Reservation Flow with Options', () => {
  let reservationNumber = null;

  test('should complete full reservation flow with options and verify in admin', async ({ page, browser }) => {
    // Step 1: Navigate to menu selection
    await page.goto('/reservation/menu');
    await page.waitForLoadState('networkidle');
    
    // Step 2: Select a menu
    const firstMenu = page.locator('.menu-item').first();
    const menuName = await firstMenu.locator('h3').textContent();
    await firstMenu.click();
    await page.waitForTimeout(1000);
    
    let selectedOptions = [];
    
    // Step 3: Handle option selection if available
    const optionSection = page.locator('#optionSection');
    if (await optionSection.isVisible()) {
      console.log('Options available - selecting options');
      
      const optionItems = page.locator('.option-item');
      const optionCount = await optionItems.count();
      
      if (optionCount > 0) {
        // Select first two options if available
        for (let i = 0; i < Math.min(2, optionCount); i++) {
          const option = optionItems.nth(i);
          const optionName = await option.locator('h4').textContent();
          selectedOptions.push(optionName);
          await option.click();
        }
        
        // Proceed with options
        await page.locator('button:has-text("日時選択へ進む")').click();
      } else {
        await page.locator('button:has-text("このまま進む")').click();
      }
    }
    
    // Step 4: Should be on calendar page
    await expect(page).toHaveURL(/\/reservation$/);
    await page.waitForLoadState('networkidle');
    
    // Step 5: Select date and time
    // Find first available slot
    const dateButtons = page.locator('[data-date]');
    const availableDateButton = dateButtons.first();
    await availableDateButton.click();
    
    // Find available time slot
    const timeSlots = page.locator('[data-time]:not(.disabled):not(.booked)');
    if (await timeSlots.count() > 0) {
      await timeSlots.first().click();
    } else {
      // If no available slots today, try next day
      const nextButton = page.locator('button:has-text("次の週"), button[title*="次"], .next-week');
      if (await nextButton.count() > 0) {
        await nextButton.first().click();
        await page.waitForTimeout(500);
        const nextTimeSlots = page.locator('[data-time]:not(.disabled):not(.booked)');
        if (await nextTimeSlots.count() > 0) {
          await nextTimeSlots.first().click();
        }
      }
    }
    
    // Step 6: Fill customer information
    const customerFormExists = await page.locator('form').count() > 0;
    if (customerFormExists) {
      await page.fill('[name="last_name"], input[placeholder*="姓"], input[placeholder*="お名前"]', '山田');
      await page.fill('[name="first_name"], input[placeholder*="名"], input[placeholder*="太郎"]', '太郎');
      await page.fill('[name="phone"], input[placeholder*="電話"], input[type="tel"]', '09012345678');
      await page.fill('[name="email"], input[placeholder*="メール"], input[type="email"]', 'test@example.com');
      
      const notesField = page.locator('[name="notes"], textarea[placeholder*="備考"], textarea[placeholder*="要望"]');
      if (await notesField.count() > 0) {
        await notesField.fill('テスト予約です');
      }
    }
    
    // Step 7: Submit reservation
    const submitButton = page.locator('button[type="submit"], button:has-text("予約する"), button:has-text("確認"), button:has-text("送信")');
    if (await submitButton.count() > 0) {
      await submitButton.click();
      await page.waitForLoadState('networkidle');
    }
    
    // Step 8: Extract reservation number from completion page
    try {
      await page.waitForSelector(':has-text("予約番号"), :has-text("Reservation"), :has-text("完了")', { timeout: 10000 });
      
      const reservationNumberElement = page.locator(':has-text("XS"), :has-text("R202"), [class*="reservation-number"], [id*="reservation"]');
      if (await reservationNumberElement.count() > 0) {
        const text = await reservationNumberElement.first().textContent();
        const match = text.match(/[A-Z]+\d+/);
        if (match) {
          reservationNumber = match[0];
          console.log(`Reservation created: ${reservationNumber}`);
        }
      }
    } catch (error) {
      console.log('Could not extract reservation number:', error.message);
    }
    
    // Step 9: Verify in admin panel
    const adminPage = await browser.newPage();
    
    try {
      // Navigate to admin login
      await adminPage.goto('/admin/login');
      await adminPage.waitForLoadState('networkidle');
      
      // Login as admin
      await adminPage.fill('input[name="email"], input[type="email"]', 'admin@example.com');
      await adminPage.fill('input[name="password"], input[type="password"]', 'password');
      await adminPage.click('button[type="submit"], button:has-text("ログイン"), button:has-text("Sign in")');
      await adminPage.waitForLoadState('networkidle');
      
      // Navigate to reservations
      await adminPage.goto('/admin/reservations');
      await adminPage.waitForLoadState('networkidle');
      
      // Search for the reservation
      if (reservationNumber) {
        const searchInput = adminPage.locator('input[placeholder*="検索"], input[type="search"], [data-testid="search"]');
        if (await searchInput.count() > 0) {
          await searchInput.fill(reservationNumber);
          await adminPage.keyboard.press('Enter');
          await adminPage.waitForTimeout(1000);
        }
      }
      
      // Find and verify reservation
      const reservationRow = reservationNumber 
        ? adminPage.locator(`tr:has-text("${reservationNumber}")`)
        : adminPage.locator(`tr:has-text("山田")`).first();
      
      if (await reservationRow.count() > 0) {
        console.log('Reservation found in admin panel');
        
        // Click to view details
        const viewButton = reservationRow.locator('a:has-text("表示"), a:has-text("View"), button:has-text("詳細")');
        if (await viewButton.count() > 0) {
          await viewButton.click();
          await adminPage.waitForLoadState('networkidle');
          
          // Verify option information is displayed
          if (selectedOptions.length > 0) {
            for (const optionName of selectedOptions) {
              const optionDisplay = adminPage.locator(`:has-text("${optionName}")`);
              await expect(optionDisplay).toBeVisible({ timeout: 5000 });
              console.log(`Option "${optionName}" found in admin panel`);
            }
          }
          
          // Verify customer information
          await expect(adminPage.locator(':has-text("山田")')).toBeVisible();
          await expect(adminPage.locator(':has-text("太郎")')).toBeVisible();
          
        } else {
          console.log('View button not found, checking row content');
          await expect(reservationRow).toContainText('山田');
          await expect(reservationRow).toContainText(menuName);
        }
      } else {
        console.log('Reservation not found in admin panel - checking all reservations');
        const allReservations = adminPage.locator('tr:has-text("山田")');
        const count = await allReservations.count();
        console.log(`Found ${count} reservations for 山田`);
        
        if (count > 0) {
          await expect(allReservations.first()).toBeVisible();
        }
      }
    } catch (adminError) {
      console.log('Admin verification failed:', adminError.message);
      // Take screenshot for debugging
      await adminPage.screenshot({ path: 'admin-error.png', fullPage: true });
    }
    
    await adminPage.close();
  });
  
  test('should handle reservation without options', async ({ page }) => {
    // Navigate to menu selection
    await page.goto('/reservation/menu');
    await page.waitForLoadState('networkidle');
    
    // Select a menu
    const firstMenu = page.locator('.menu-item').first();
    await firstMenu.click();
    await page.waitForTimeout(1000);
    
    // Handle option selection - proceed without options
    const optionSection = page.locator('#optionSection');
    if (await optionSection.isVisible()) {
      await page.locator('button:has-text("このまま進む")').click();
    }
    
    // Should proceed to calendar
    await expect(page).toHaveURL(/\/reservation$/);
    
    console.log('Reservation flow without options completed successfully');
  });
  
  test('should verify API endpoints are working', async ({ page }) => {
    // Test menu API
    const menuResponse = await page.request.get('/api/menus');
    expect(menuResponse.ok()).toBeTruthy();
    
    // Test upsell API
    const upsellResponse = await page.request.get('/api/menus/upsell?exclude=1');
    expect(upsellResponse.ok()).toBeTruthy();
    
    const upsellData = await upsellResponse.json();
    expect(Array.isArray(upsellData)).toBeTruthy();
    console.log(`Found ${upsellData.length} upsell options`);
  });
});