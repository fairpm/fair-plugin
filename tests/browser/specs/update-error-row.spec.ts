import { test, expect } from '@playwright/test';

/**
 * Update error row test — verifies FAIR-specific error messages on plugins page.
 *
 * When DID-based plugin updates fail, FAIR caches the error as a transient
 * and displays it in a row beneath the plugin on the plugins list screen.
 *
 * Prerequisites:
 *  - Docker: tests/sites/browser-test/docker-compose.yml up
 *  - WP installed and seeded with browser_admin user
 */

test.describe('Update error row', () => {

  test.beforeEach(async ({ page }) => {
    await page.goto('/wp-admin/plugins.php');
    await page.waitForLoadState('networkidle');
  });

  test('plugins page renders', async ({ page }) => {
    const heading = page.locator('h1').first();
    await expect(heading).toBeVisible();
    await expect(heading).toContainText(/Plugins/i);
  });

  test('FAIR plugin error row visible when transient is set', async ({ page }) => {
    // The seed script creates a dummy plugin and sets a fair_update_error
    // transient. The error row should appear beneath the plugin in the list.

    const errorRows = page.locator('.plugin-update-tr');
    await expect(errorRows.first()).toBeVisible({ timeout: 5000 });

    const firstError = errorRows.first();
    const text = await firstError.textContent();
    expect(text).toContain('Could not fetch');
    expect(text).toContain('Update checks paused');
  });

  test('active plugin row has correct structure', async ({ page }) => {
    // Verify the plugins table has the expected plugin rows.
    const fairRow = page.locator('tr[data-slug="fair-plugin"]');
    const count = await fairRow.count();
    expect(count).toBeGreaterThan(0);

    if (count > 0) {
      // The plugin name should contain "FAIR".
      const nameEl = fairRow.first().locator('.plugin-title strong');
      await expect(nameEl).toBeVisible();
    }
  });

  test('no JavaScript errors on plugins page', async ({ page }) => {
    // Listen for console errors.
    const errors: string[] = [];
    page.on('pageerror', error => errors.push(error.message));

    await page.reload();
    await page.waitForLoadState('networkidle');

    // Give JS time to settle.
    await page.waitForTimeout(1000);

    expect(errors).toHaveLength(0);
  });
});
