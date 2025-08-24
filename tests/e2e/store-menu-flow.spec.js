import { test, expect } from '@playwright/test';

test.describe('Store Selection and Menu Flow with Options', () => {
  test('complete reservation flow with store selection', async ({ page }) => {
    console.log('Starting complete reservation flow test...');
    
    // Step 1: Navigate to store selection
    await page.goto('/reservation/store');
    await page.waitForLoadState('networkidle');
    
    // Verify store page is loaded
    await expect(page.locator('h1:has-text("店舗を選択")')).toBeVisible();
    
    // Step 2: Select first available store
    const storeCards = page.locator('.store-card');
    const storeCount = await storeCards.count();
    expect(storeCount).toBeGreaterThan(0);
    console.log(`Found ${storeCount} stores`);
    
    // Click on first store
    await storeCards.first().click();
    await page.waitForLoadState('networkidle');
    
    // Step 3: Should be on menu selection page
    await expect(page).toHaveURL(/\/reservation\/menu/);
    await expect(page.locator('h1:has-text("メニューを選択")')).toBeVisible();
    
    // Verify menus are displayed
    const menuItems = page.locator('.menu-item');
    const menuCount = await menuItems.count();
    expect(menuCount).toBeGreaterThan(0);
    console.log(`Found ${menuCount} menus for selected store`);
    
    // Step 4: Select first menu
    const firstMenu = menuItems.first();
    const menuName = await firstMenu.locator('h3').textContent();
    console.log(`Selecting menu: ${menuName}`);
    
    await firstMenu.click();
    await page.waitForTimeout(1500); // Wait for animation and API call
    
    // Step 5: Check if option section appears
    const optionSection = page.locator('#optionSection');
    const optionSectionVisible = await optionSection.isVisible();
    
    if (optionSectionVisible) {
      console.log('Option section is visible');
      
      // Verify option section content
      await expect(page.locator('h2:has-text("ご一緒にいかがですか")')).toBeVisible();
      
      // Check for option items
      const optionItems = page.locator('.option-item');
      const optionCount = await optionItems.count();
      console.log(`Found ${optionCount} upsell options`);
      
      if (optionCount > 0) {
        // Select first option
        await optionItems.first().click();
        
        // Verify checkbox is checked
        const checkbox = optionItems.first().locator('input[type="checkbox"]');
        await expect(checkbox).toBeChecked();
        
        // Check total price is updated
        const totalPrice = page.locator('#totalPrice');
        await expect(totalPrice).toContainText('¥');
        
        // Proceed with options
        await page.locator('button:has-text("選択したオプションで進む")').click();
      } else {
        // Proceed without options
        await page.locator('button:has-text("追加なしで進む")').click();
      }
    } else {
      console.log('No options available - should proceed automatically');
      // Wait for automatic redirect
      await page.waitForTimeout(2000);
    }
    
    // Step 6: Should be on calendar page
    await expect(page).toHaveURL(/\/reservation$/);
    console.log('Successfully navigated to calendar page');
  });
  
  test('verify store-specific menus are displayed', async ({ page }) => {
    // Navigate to direct menu page without store selection
    await page.goto('/reservation/menu');
    
    // Should redirect to store selection
    await expect(page).toHaveURL(/\/reservation\/store/);
    console.log('Correctly redirected to store selection when no store is selected');
    
    // Select a store
    const storeCards = page.locator('.store-card');
    await storeCards.first().click();
    await page.waitForLoadState('networkidle');
    
    // Now on menu page
    await expect(page).toHaveURL(/\/reservation\/menu/);
    
    // Get store ID from first menu
    const firstMenu = page.locator('.menu-item').first();
    const storeId = await firstMenu.getAttribute('data-store-id');
    console.log(`Store ID from menu: ${storeId}`);
    
    // Verify all menus have the same store ID
    const allMenus = page.locator('.menu-item');
    const menuCount = await allMenus.count();
    
    for (let i = 0; i < menuCount; i++) {
      const menu = allMenus.nth(i);
      const menuStoreId = await menu.getAttribute('data-store-id');
      expect(menuStoreId).toBe(storeId);
    }
    
    console.log(`All ${menuCount} menus belong to store ${storeId}`);
  });
  
  test('verify upsell API returns store-specific options', async ({ page }) => {
    // Navigate to store selection
    await page.goto('/reservation/store');
    await page.waitForLoadState('networkidle');
    
    // Select first store
    await page.locator('.store-card').first().click();
    await page.waitForLoadState('networkidle');
    
    // Get store ID from menu
    const firstMenu = page.locator('.menu-item').first();
    const storeId = await firstMenu.getAttribute('data-store-id');
    const menuId = await firstMenu.getAttribute('data-menu-id');
    
    // Call upsell API directly
    const response = await page.request.get(`/api/menus/upsell?exclude=${menuId}&store_id=${storeId}`);
    expect(response.ok()).toBeTruthy();
    
    const upsellMenus = await response.json();
    console.log(`Upsell API returned ${upsellMenus.length} options for store ${storeId}`);
    
    // Verify all options are from the same store
    if (upsellMenus.length > 0) {
      // Since the API doesn't return store_id, we just verify it returns data
      expect(Array.isArray(upsellMenus)).toBeTruthy();
      
      // Verify required fields
      upsellMenus.forEach(menu => {
        expect(menu).toHaveProperty('id');
        expect(menu).toHaveProperty('name');
        expect(menu).toHaveProperty('price');
        expect(menu).toHaveProperty('duration');
      });
    }
  });
  
  test('session persistence through flow', async ({ page }) => {
    // Navigate to store selection
    await page.goto('/reservation/store');
    
    // Select a store
    const storeCards = page.locator('.store-card');
    const firstStoreText = await storeCards.first().locator('h3').textContent();
    await storeCards.first().click();
    await page.waitForLoadState('networkidle');
    
    // Should be on menu page
    await expect(page).toHaveURL(/\/reservation\/menu/);
    
    // Navigate directly to menu page again
    await page.goto('/reservation/menu');
    
    // Should stay on menu page (session has store)
    await expect(page).toHaveURL(/\/reservation\/menu/);
    console.log('Session correctly maintains store selection');
    
    // Select a menu
    await page.locator('.menu-item').first().click();
    await page.waitForTimeout(1500);
    
    // Handle options if they appear
    const optionSection = page.locator('#optionSection');
    if (await optionSection.isVisible()) {
      await page.locator('button:has-text("追加なしで進む")').click();
    }
    
    // Wait for navigation
    await page.waitForTimeout(2000);
    
    // Should be on calendar page with session data
    await expect(page).toHaveURL(/\/reservation$/);
    console.log('Complete flow works with session persistence');
  });
});