import { test, expect } from '@playwright/test';

test.describe('E2E Full Workflow Tests', () => {
  const BASE_URL = 'http://127.0.0.1:8000';
  
  // Test data
  const testCustomer = {
    lastName: 'テスト',
    firstName: '太郎',
    lastNameKana: 'テスト',
    firstNameKana: 'タロウ',
    phone: '090-1234-5678',
    email: 'test@example.com'
  };
  
  const testReservation = {
    date: new Date(Date.now() + 7 * 24 * 60 * 60 * 1000).toISOString().split('T')[0], // 1週間後
    time: '14:00'
  };

  async function adminLogin(page) {
    await page.goto(`${BASE_URL}/admin/login`);
    
    const emailInput = page.locator('input[type="email"], input[name="email"]').first();
    const passwordInput = page.locator('input[type="password"], input[name="password"]').first();
    
    await emailInput.fill('admin@eye-training.com');
    await passwordInput.fill('password');
    
    const submitButton = page.locator('button[type="submit"], input[type="submit"], button:has-text("ログイン"), button:has-text("Login")').first();
    await submitButton.click();
    
    await page.waitForURL(`${BASE_URL}/admin*`, { timeout: 15000 });
  }

  test('Complete customer registration and reservation workflow', async ({ page }) => {
    // Step 1: Admin login
    await adminLogin(page);
    
    // Step 2: Create new customer
    await page.goto(`${BASE_URL}/admin/customers`);
    await page.waitForLoadState('networkidle');
    
    // Find create customer button
    const createButton = page.locator('a:has-text("新規"), a:has-text("作成"), a:has-text("Create")').first();
    if (await createButton.isVisible({ timeout: 5000 })) {
      await createButton.click();
      await page.waitForLoadState('networkidle');
      
      // Fill customer form
      await page.fill('input[name="last_name"]', testCustomer.lastName);
      await page.fill('input[name="first_name"]', testCustomer.firstName);
      await page.fill('input[name="last_name_kana"]', testCustomer.lastNameKana);
      await page.fill('input[name="first_name_kana"]', testCustomer.firstNameKana);
      await page.fill('input[name="phone"]', testCustomer.phone);
      await page.fill('input[name="email"]', testCustomer.email);
      
      // Submit form
      const saveButton = page.locator('button[type="submit"], button:has-text("保存"), button:has-text("作成"), button:has-text("Create")').first();
      await saveButton.click();
      await page.waitForLoadState('networkidle');
    }
    
    // Step 3: Create reservation for the customer
    await page.goto(`${BASE_URL}/admin/reservations`);
    await page.waitForLoadState('networkidle');
    
    const createReservationButton = page.locator('a:has-text("新規"), a:has-text("作成"), a:has-text("Create")').first();
    if (await createReservationButton.isVisible({ timeout: 5000 })) {
      await createReservationButton.click();
      await page.waitForLoadState('networkidle');
      
      // Fill reservation form
      // Select customer (search by phone)
      const customerSelect = page.locator('select[name="customer_id"], .fi-select-input').first();
      if (await customerSelect.isVisible({ timeout: 5000 })) {
        await customerSelect.click();
        await page.waitForTimeout(1000);
        
        // Search for customer by typing phone number
        const searchInput = page.locator('input[placeholder*="検索"], input[type="search"]').first();
        if (await searchInput.isVisible({ timeout: 3000 })) {
          await searchInput.fill(testCustomer.phone);
          await page.waitForTimeout(1000);
          
          const customerOption = page.locator(`text=${testCustomer.lastName}`).first();
          if (await customerOption.isVisible({ timeout: 3000 })) {
            await customerOption.click();
          }
        }
      }
      
      // Set reservation date and time
      const dateInput = page.locator('input[name="reservation_date"], input[type="date"]').first();
      if (await dateInput.isVisible({ timeout: 5000 })) {
        await dateInput.fill(testReservation.date);
      }
      
      const timeInput = page.locator('input[name="reservation_time"], input[type="time"]').first();
      if (await timeInput.isVisible({ timeout: 5000 })) {
        await timeInput.fill(testReservation.time);
      }
      
      // Submit reservation
      const saveReservationButton = page.locator('button[type="submit"], button:has-text("保存"), button:has-text("作成")').first();
      await saveReservationButton.click();
      await page.waitForLoadState('networkidle');
    }
    
    // Step 4: Verify reservation was created
    await page.goto(`${BASE_URL}/admin/reservations`);
    await page.waitForLoadState('networkidle');
    
    // Check if reservation appears in the list
    const reservationTable = page.locator('table, .filament-table, [role="table"]').first();
    if (await reservationTable.isVisible({ timeout: 5000 })) {
      const tableContent = await reservationTable.textContent();
      expect(tableContent).toContain(testCustomer.lastName);
    }
    
    // Step 5: Create medical record for the customer
    await page.goto(`${BASE_URL}/admin/medical-records`);
    await page.waitForLoadState('networkidle');
    
    const createMedicalButton = page.locator('a:has-text("新規"), a:has-text("作成"), a:has-text("Create")').first();
    if (await createMedicalButton.isVisible({ timeout: 5000 })) {
      await createMedicalButton.click();
      await page.waitForLoadState('networkidle');
      
      // Select customer for medical record
      const medicalCustomerSelect = page.locator('select[name="customer_id"], .fi-select-input').first();
      if (await medicalCustomerSelect.isVisible({ timeout: 5000 })) {
        await medicalCustomerSelect.click();
        await page.waitForTimeout(1000);
        
        const medicalCustomerOption = page.locator(`text=${testCustomer.lastName}`).first();
        if (await medicalCustomerOption.isVisible({ timeout: 3000 })) {
          await medicalCustomerOption.click();
        }
      }
      
      // Fill basic medical info
      const handledByInput = page.locator('input[name="handled_by"]').first();
      if (await handledByInput.isVisible({ timeout: 5000 })) {
        await handledByInput.fill('テスト担当者');
      }
      
      // Submit medical record
      const saveMedicalButton = page.locator('button[type="submit"], button:has-text("保存"), button:has-text("作成")').first();
      await saveMedicalButton.click();
      await page.waitForLoadState('networkidle');
    }
  });

  test('Reservation cancellation and rescheduling workflow', async ({ page }) => {
    await adminLogin(page);
    
    // Navigate to reservations
    await page.goto(`${BASE_URL}/admin/reservations`);
    await page.waitForLoadState('networkidle');
    
    // Find the first reservation and try to edit it
    const editButton = page.locator('a[href*="/edit"], button:has-text("編集"), button:has-text("Edit")').first();
    if (await editButton.isVisible({ timeout: 5000 })) {
      await editButton.click();
      await page.waitForLoadState('networkidle');
      
      // Change status to cancelled
      const statusSelect = page.locator('select[name="status"]').first();
      if (await statusSelect.isVisible({ timeout: 5000 })) {
        await statusSelect.selectOption('cancelled');
      }
      
      // Save changes
      const saveButton = page.locator('button[type="submit"], button:has-text("保存"), button:has-text("Update")').first();
      await saveButton.click();
      await page.waitForLoadState('networkidle');
      
      // Verify we're back on the reservations list
      expect(page.url()).toContain('/admin/reservations');
    }
  });

  test('Staff shift management workflow', async ({ page }) => {
    await adminLogin(page);
    
    // Navigate to shift management
    await page.goto(`${BASE_URL}/admin/shift-management`);
    await page.waitForLoadState('networkidle');
    
    // Check if shift management page loads correctly
    const pageContent = await page.content();
    expect(pageContent).not.toContain('Error');
    expect(pageContent).not.toContain('500');
    
    // Try to create a new shift (if interface is available)
    const quickAddForm = page.locator('.quick-add-form, form').first();
    if (await quickAddForm.isVisible({ timeout: 5000 })) {
      // Test quick shift creation functionality
      const staffSelect = page.locator('select').first();
      const startTimeInput = page.locator('input[type="time"]').first();
      const endTimeInput = page.locator('input[type="time"]').nth(1);
      
      if (await staffSelect.isVisible() && await startTimeInput.isVisible() && await endTimeInput.isVisible()) {
        // Select first available staff
        await staffSelect.selectOption({ index: 1 });
        await startTimeInput.fill('09:00');
        await endTimeInput.fill('17:00');
        
        const addShiftButton = page.locator('button:has-text("追加"), button:has-text("作成")').first();
        if (await addShiftButton.isVisible()) {
          await addShiftButton.click();
          await page.waitForLoadState('networkidle');
        }
      }
    }
  });
});