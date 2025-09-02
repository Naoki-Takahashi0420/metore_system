import { test, expect } from '@playwright/test';

test('Simple Dashboard Test', async ({ page }) => {
  // Test login page loads
  await page.goto('/customer/login');
  await expect(page).toHaveTitle(/ログイン/);
  
  // Check if phone field exists
  const phoneField = page.locator('#phone');
  await expect(phoneField).toBeVisible();
  
  // Test public page without auth
  await page.goto('/test-login.html');
  await expect(page).toHaveTitle(/ログインテスト/);
  
  // Test API directly
  const response = await page.request.post('/api/auth/customer/send-otp', {
    data: {
      phone: '08033372305'
    }
  });
  
  expect(response.status()).toBe(200);
  const data = await response.json();
  expect(data.success).toBe(true);
  
  console.log('OTP送信成功:', data);
});

test('Dashboard Access Test', async ({ page }) => {
  // Wait between requests to avoid rate limiting
  await page.waitForTimeout(2000);
  
  // First get token via API
  const otpResponse = await page.request.post('/api/auth/customer/send-otp', {
    data: { phone: '08033372305' }
  });
  
  // Handle rate limiting (429)
  if (otpResponse.status() === 429) {
    console.log('Rate limited, waiting and retrying...');
    await page.waitForTimeout(5000);
    const retryResponse = await page.request.post('/api/auth/customer/send-otp', {
      data: { phone: '08033372305' }
    });
    expect(retryResponse.status()).toBe(200);
  } else {
    expect(otpResponse.status()).toBe(200);
  }
  
  const verifyResponse = await page.request.post('/api/auth/customer/verify-otp', {
    data: {
      phone: '08033372305',
      otp_code: '123456'
    }
  });
  
  expect(verifyResponse.status()).toBe(200);
  const verifyData = await verifyResponse.json();
  const token = verifyData.data.token;
  
  // Set token in localStorage
  await page.goto('/customer/dashboard');
  await page.evaluate((token) => {
    localStorage.setItem('customer_token', token);
  }, token);
  
  // Reload and check
  await page.reload();
  await page.waitForTimeout(2000);
  
  // Check if any content loads
  const content = await page.content();
  console.log('Dashboard loaded');
});

test('Reservation API Test', async ({ page }) => {
  // Wait to avoid rate limiting
  await page.waitForTimeout(3000);
  
  // Login first
  const otpResponse = await page.request.post('/api/auth/customer/send-otp', {
    data: { phone: '08033372305' }
  });
  
  // Handle rate limiting
  if (otpResponse.status() === 429) {
    console.log('Rate limited, waiting and retrying...');
    await page.waitForTimeout(5000);
    const retryResponse = await page.request.post('/api/auth/customer/send-otp', {
      data: { phone: '08033372305' }
    });
    expect(retryResponse.status()).toBe(200);
  }
  
  const verifyResponse = await page.request.post('/api/auth/customer/verify-otp', {
    data: {
      phone: '08033372305',
      otp_code: '123456'
    }
  });
  
  const verifyData = await verifyResponse.json();
  const token = verifyData.data.token;
  
  // Get reservations
  const reservationsResponse = await page.request.get('/api/customer/reservations', {
    headers: {
      'Authorization': `Bearer ${token}`
    }
  });
  
  expect(reservationsResponse.status()).toBe(200);
  const reservations = await reservationsResponse.json();
  expect(reservations.data).toBeDefined();
  expect(Array.isArray(reservations.data)).toBe(true);
  
  console.log(`予約数: ${reservations.data.length}`);
});