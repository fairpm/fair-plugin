import { chromium, FullConfig } from '@playwright/test';

/**
 * Global setup: log in as browser_admin and save browser storage state.
 * Runs once before all tests. All specs reuse the authenticated state.
 */
async function globalSetup(config: FullConfig) {
  const browser = await chromium.launch();
  const context = await browser.newContext();
  const page = await context.newPage();

  const baseURL = config.projects[0].use.baseURL || 'http://localhost:8899';

  // Navigate to login.
  await page.goto(`${baseURL}/wp-login.php`);

  // Fill credentials.
  await page.fill('#user_login', 'browser_admin');
  await page.fill('#user_pass', 'browser_test_password');

  // Submit and wait for admin dashboard.
  await Promise.all([
    page.waitForURL('**/wp-admin/**', { timeout: 15000 }),
    page.click('#wp-submit'),
  ]);

  // Verify we're logged in.
  const body = await page.textContent('body');
  if (!body?.includes('Dashboard')) {
    throw new Error('Login failed — Dashboard not found after login.');
  }

  console.log('✓ Authenticated as browser_admin');

  // Save storage state for all tests to reuse.
  await page.context().storageState({ path: './.auth/admin.json' });

  await browser.close();
}

export default globalSetup;
