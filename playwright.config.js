import { defineConfig, devices } from '@playwright/test';

export default defineConfig({
  testDir: './tests',
  fullyParallel: false, // Set to false for production testing to avoid conflicts
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 2 : 1,
  workers: 1, // Use single worker for production testing
  reporter: [
    ['html', { open: 'never' }],
    ['list'],
    ['json', { outputFile: 'test-results.json' }]
  ],
  use: {
    baseURL: process.env.PRODUCTION_TEST ? 'http://13.115.38.179' : 'http://127.0.0.1:8000',
    trace: 'on-first-retry',
    screenshot: 'only-on-failure',
    video: 'retain-on-failure',
    actionTimeout: 30000,
    navigationTimeout: 30000,
  },
  projects: [
    {
      name: 'chromium',
      use: { ...devices['Desktop Chrome'] },
    },
  ],
  // Only start local server if not testing production
  webServer: process.env.PRODUCTION_TEST ? undefined : {
    command: 'php artisan serve --port=8000',
    port: 8000,
    reuseExistingServer: !process.env.CI,
  },
  timeout: 60000,
  expect: {
    timeout: 10000,
  },
});