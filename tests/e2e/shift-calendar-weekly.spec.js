import { test, expect } from '@playwright/test';

test.describe('��ȫ����1!B��h:', () => {
    test('1!�����nB��h:���', async ({ page }) => {
        // ��
        await page.goto('/admin/login');
        await page.getByLabel('�����').fill('superadmin@xsyumeno.com');
        await page.getByLabel('ѹ���').fill('password');
        await page.getByRole('button', { name: '��' }).click();
        
        // ��ȡ���k��
        await page.getByRole('link', { name: '��ȡ' }).click();
        
        // �����h:ܿ��ï
        await page.getByRole('link', { name: '�����' }).click();
        
        // 1!�Ӳ����ܿ�Lh:U��Sh���
        const prevWeekButton = page.locator('button[wire\\:click="previousWeek"]');
        const nextWeekButton = page.locator('button[wire\\:click="nextWeek"]');
        await expect(prevWeekButton).toBeVisible();
        await expect(nextWeekButton).toBeVisible();
        
        // B������Lh:U��Sh���
        const timeHeader = page.locator('th:has-text("B�")');
        await expect(timeHeader).toBeVisible();
        
        // 30;nB�Lh:U��Sh���
        const timeSlots = ['08:00', '08:30', '09:00', '09:30', '10:00'];
        for (const time of timeSlots) {
            const timeCell = page.locator(`td:has-text("${time}")`).first();
            await expect(timeCell).toBeVisible();
        }
        
        // 7�n��Lh:U��Sh���
        const dayHeaders = page.locator('thead th').filter({ hasText: /\d{1,2}\/\d{1,2}/ });
        const dayCount = await dayHeaders.count();
        expect(dayCount).toBe(7);
        
        // �Lh:U��Sh���
        await expect(page.locator('h3:has-text("�")')).toBeVisible();
        await expect(page.locator('span:has-text("��")')).toBeVisible();
        await expect(page.locator('span:has-text("��-")')).toBeVisible();
        await expect(page.locator('span:has-text("�ٌ�")')).toBeVisible();
        
        console.log(' ��ȫ����n1!B��h:Lc8k�\WfD~Y');
    });
    
    test('1!�Ӳ����_�n��', async ({ page }) => {
        // ��
        await page.goto('/admin/login');
        await page.getByLabel('�����').fill('superadmin@xsyumeno.com');
        await page.getByLabel('ѹ���').fill('password');
        await page.getByRole('button', { name: '��' }).click();
        
        // ��ȫ�������k��
        await page.goto('/admin/shifts/calendar');
        
        // �(n1n����֗
        const weekRangeText = await page.locator('h2').textContent();
        console.log('�(n1:', weekRangeText);
        
        // Mn1k��
        await page.locator('button[wire\\:click="previousWeek"]').click();
        await page.waitForTimeout(500); // Livewiren����d
        
        const prevWeekRangeText = await page.locator('h2').textContent();
        console.log('Mn1:', prevWeekRangeText);
        expect(prevWeekRangeText).not.toBe(weekRangeText);
        
        // !n1k��Ck;�	
        await page.locator('button[wire\\:click="nextWeek"]').click();
        await page.waitForTimeout(500); // Livewiren����d
        
        const currentWeekRangeText = await page.locator('h2').textContent();
        console.log('�(n1;c_�	:', currentWeekRangeText);
        
        console.log(' 1!�Ӳ����_�Lc8k�\WfD~Y');
    });
});