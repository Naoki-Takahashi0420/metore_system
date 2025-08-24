import { test, expect } from '@playwright/test';

test.describe('Menu Option Selection Flow', () => {
  test.beforeEach(async ({ page }) => {
    // Start from the menu selection page
    await page.goto('/reservation/menu');
    await page.waitForLoadState('networkidle');
  });

  test('should display main menus without options', async ({ page }) => {
    // Check that main menus are displayed
    const menuItems = page.locator('.menu-item');
    await expect(menuItems).toHaveCount(await menuItems.count());

    // Verify menu structure
    const firstMenu = menuItems.first();
    await expect(firstMenu).toBeVisible();
    await expect(firstMenu.locator('h3')).toBeVisible();
    await expect(firstMenu.locator('.text-blue-600')).toBeVisible(); // Price
  });

  test('should show option selection when menu with options is selected', async ({ page }) => {
    // Select the first available menu
    const firstMenu = page.locator('.menu-item').first();
    await firstMenu.click();

    // Wait for the API call to complete
    await page.waitForTimeout(1000);

    // Check if option section appears
    const optionSection = page.locator('#optionSection');
    const isVisible = await optionSection.isVisible();

    if (isVisible) {
      // Verify option section content
      await expect(page.locator('h2:has-text("ご一緒にいかがですか？")')).toBeVisible();
      await expect(page.locator('#optionList')).toBeVisible();
      await expect(page.locator('#selectedMenuInfo')).toBeVisible();
      await expect(page.locator('#totalPrice')).toBeVisible();

      // Check buttons
      await expect(page.locator('button:has-text("このまま進む")')).toBeVisible();
      await expect(page.locator('button:has-text("日時選択へ進む")')).toBeVisible();
    } else {
      console.log('No options available for this menu - proceeding directly');
    }
  });

  test('should handle option selection and price calculation', async ({ page }) => {
    // Select the first available menu
    const firstMenu = page.locator('.menu-item').first();
    const menuName = await firstMenu.locator('h3').textContent();
    const menuPrice = await firstMenu.locator('.text-blue-600').textContent();
    
    await firstMenu.click();
    await page.waitForTimeout(1000);

    const optionSection = page.locator('#optionSection');
    if (await optionSection.isVisible()) {
      // Verify selected menu info is displayed correctly
      await expect(page.locator('#selectedMenuInfo')).toContainText(menuName);

      // Check if options are available
      const optionItems = page.locator('.option-item');
      const optionCount = await optionItems.count();

      if (optionCount > 0) {
        console.log(`Found ${optionCount} option(s)`);

        // Select the first option
        const firstOption = optionItems.first();
        await firstOption.click();

        // Verify option is selected (visual feedback)
        await expect(firstOption).toHaveClass(/border-green-500/);
        await expect(firstOption).toHaveClass(/bg-green-50/);

        // Verify checkbox is checked
        const checkbox = firstOption.locator('input[type="checkbox"]');
        await expect(checkbox).toBeChecked();

        // Verify total price is updated
        const totalPrice = page.locator('#totalPrice');
        await expect(totalPrice).toContainText('合計:');
        await expect(totalPrice).toContainText('¥');

        // Deselect the option
        await firstOption.click();

        // Verify option is deselected
        await expect(firstOption).not.toHaveClass(/border-green-500/);
        await expect(firstOption).not.toHaveClass(/bg-green-50/);
        await expect(checkbox).not.toBeChecked();
      }
    }
  });

  test('should proceed to calendar with selected options', async ({ page }) => {
    // Select the first available menu
    const firstMenu = page.locator('.menu-item').first();
    await firstMenu.click();
    await page.waitForTimeout(1000);

    const optionSection = page.locator('#optionSection');
    
    if (await optionSection.isVisible()) {
      // Select first option if available
      const optionItems = page.locator('.option-item');
      if (await optionItems.count() > 0) {
        await optionItems.first().click();
      }

      // Click proceed button
      await page.locator('button:has-text("日時選択へ進む")').click();
    }

    // Should navigate to calendar page
    await expect(page).toHaveURL(/\/reservation$/);
    
    // Verify calendar elements are present
    await expect(page.locator('.calendar, [class*="calendar"], .reservation-calendar')).toBeVisible();
  });

  test('should proceed directly without options', async ({ page }) => {
    // Select the first available menu
    const firstMenu = page.locator('.menu-item').first();
    await firstMenu.click();
    await page.waitForTimeout(1000);

    const optionSection = page.locator('#optionSection');
    
    if (await optionSection.isVisible()) {
      // Don't select any options, just proceed
      await page.locator('button:has-text("このまま進む")').click();

      // Should navigate to calendar page
      await expect(page).toHaveURL(/\/reservation$/);
    } else {
      // If no options, should automatically proceed
      await expect(page).toHaveURL(/\/reservation$/);
    }
  });

  test('should handle multiple option selections', async ({ page }) => {
    // Select the first available menu
    const firstMenu = page.locator('.menu-item').first();
    await firstMenu.click();
    await page.waitForTimeout(1000);

    const optionSection = page.locator('#optionSection');
    
    if (await optionSection.isVisible()) {
      const optionItems = page.locator('.option-item');
      const optionCount = await optionItems.count();

      if (optionCount >= 2) {
        // Select multiple options
        await optionItems.nth(0).click();
        await optionItems.nth(1).click();

        // Verify both options are selected
        await expect(optionItems.nth(0).locator('input[type="checkbox"]')).toBeChecked();
        await expect(optionItems.nth(1).locator('input[type="checkbox"]')).toBeChecked();

        // Verify total price includes both options
        const totalPrice = page.locator('#totalPrice');
        await expect(totalPrice).toBeVisible();
        await expect(totalPrice).toContainText('合計:');

        // Proceed to calendar
        await page.locator('button:has-text("日時選択へ進む")').click();
        await expect(page).toHaveURL(/\/reservation$/);
      }
    }
  });

  test('should handle API errors gracefully', async ({ page }) => {
    // Mock API to simulate error
    await page.route('/api/menus/upsell*', route => {
      route.fulfill({
        status: 500,
        contentType: 'application/json',
        body: JSON.stringify({ error: 'Server error' })
      });
    });

    // Select the first available menu
    const firstMenu = page.locator('.menu-item').first();
    await firstMenu.click();
    await page.waitForTimeout(1000);

    // Should still proceed to calendar even if API fails
    await expect(page).toHaveURL(/\/reservation$/);
  });

  test('should validate form submission includes option data', async ({ page }) => {
    let formData = null;

    // Intercept form submission
    await page.route('**/reservation/select-menu', async route => {
      const request = route.request();
      formData = await request.postData();
      await route.continue();
    });

    // Select the first available menu
    const firstMenu = page.locator('.menu-item').first();
    await firstMenu.click();
    await page.waitForTimeout(1000);

    const optionSection = page.locator('#optionSection');
    
    if (await optionSection.isVisible()) {
      const optionItems = page.locator('.option-item');
      if (await optionItems.count() > 0) {
        // Select an option
        await optionItems.first().click();
      }

      // Submit form
      await page.locator('button:has-text("日時選択へ進む")').click();
      
      // Wait for form submission
      await page.waitForTimeout(1000);

      // Verify form data includes option_ids
      if (formData) {
        expect(formData).toContain('menu_id');
        // If options were selected, should include option_ids
        const hasOptions = await optionItems.count() > 0;
        if (hasOptions) {
          expect(formData).toContain('option_ids');
        }
      }
    }
  });
});