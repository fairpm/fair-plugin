import { defineConfig } from '@playwright/test';

const BASE_URL = process.env.FAIR_TEST_BASE_URL || 'http://localhost:8899';

export default defineConfig({
  testDir: './specs',
  timeout: 30000,
  retries: process.env.CI ? 2 : 0,
  use: {
    baseURL: BASE_URL,
    storageState: './.auth/admin.json',
    screenshot: 'only-on-failure',
    trace: 'retain-on-failure',
  },
  projects: [
    {
      name: 'chromium',
      use: { browserName: 'chromium' },
    },
  ],
  globalSetup: './global-setup.ts',
});
