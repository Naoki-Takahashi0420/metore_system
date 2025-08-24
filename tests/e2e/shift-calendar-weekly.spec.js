import { test, expect } from '@playwright/test';

test.describe('·ÕÈ«ìóÀü1!B“øh:', () => {
    test('1!«ìóÀünB“øh:’º', async ({ page }) => {
        // í°¤ó
        await page.goto('/admin/login');
        await page.getByLabel('áüë¢Éì¹').fill('superadmin@xsyumeno.com');
        await page.getByLabel('Ñ¹ïüÉ').fill('password');
        await page.getByRole('button', { name: 'í°¤ó' }).click();
        
        // ·ÕÈ¡Úü¸kûÕ
        await page.getByRole('link', { name: '·ÕÈ¡' }).click();
        
        // «ìóÀüh:Ü¿ó’¯êÃ¯
        await page.getByRole('link', { name: '«ìóÀü' }).click();
        
        // 1!ÊÓ²ü·çóÜ¿óLh:UŒ‹Sh’º
        const prevWeekButton = page.locator('button[wire\\:click="previousWeek"]');
        const nextWeekButton = page.locator('button[wire\\:click="nextWeek"]');
        await expect(prevWeekButton).toBeVisible();
        await expect(nextWeekButton).toBeVisible();
        
        // B“øØÃÀüLh:UŒ‹Sh’º
        const timeHeader = page.locator('th:has-text("B“")');
        await expect(timeHeader).toBeVisible();
        
        // 30;nB“Lh:UŒ‹Sh’º
        const timeSlots = ['08:00', '08:30', '09:00', '09:30', '10:00'];
        for (const time of timeSlots) {
            const timeCell = page.locator(`td:has-text("${time}")`).first();
            await expect(timeCell).toBeVisible();
        }
        
        // 7å“nÜåLh:UŒ‹Sh’º
        const dayHeaders = page.locator('thead th').filter({ hasText: /\d{1,2}\/\d{1,2}/ });
        const dayCount = await dayHeaders.count();
        expect(dayCount).toBe(7);
        
        // á‹Lh:UŒ‹Sh’º
        await expect(page.locator('h3:has-text("á‹")')).toBeVisible();
        await expect(page.locator('span:has-text("ˆš")')).toBeVisible();
        await expect(page.locator('span:has-text("äÙ-")')).toBeVisible();
        await expect(page.locator('span:has-text("äÙŒ†")')).toBeVisible();
        
        console.log(' ·ÕÈ«ìóÀün1!B“øh:Lc8kÕ\WfD~Y');
    });
    
    test('1!ÊÓ²ü·çó_ınº', async ({ page }) => {
        // í°¤ó
        await page.goto('/admin/login');
        await page.getByLabel('áüë¢Éì¹').fill('superadmin@xsyumeno.com');
        await page.getByLabel('Ñ¹ïüÉ').fill('password');
        await page.getByRole('button', { name: 'í°¤ó' }).click();
        
        // ·ÕÈ«ìóÀüÚü¸kûÕ
        await page.goto('/admin/shifts/calendar');
        
        // ş(n1nåØÄò’Ö—
        const weekRangeText = await page.locator('h2').textContent();
        console.log('ş(n1:', weekRangeText);
        
        // Mn1kûÕ
        await page.locator('button[wire\\:click="previousWeek"]').click();
        await page.waitForTimeout(500); // Livewirenô°’…d
        
        const prevWeekRangeText = await page.locator('h2').textContent();
        console.log('Mn1:', prevWeekRangeText);
        expect(prevWeekRangeText).not.toBe(weekRangeText);
        
        // !n1kûÕCk;‹	
        await page.locator('button[wire\\:click="nextWeek"]').click();
        await page.waitForTimeout(500); // Livewirenô°’…d
        
        const currentWeekRangeText = await page.locator('h2').textContent();
        console.log('ş(n1;c_Œ	:', currentWeekRangeText);
        
        console.log(' 1!ÊÓ²ü·çó_ıLc8kÕ\WfD~Y');
    });
});