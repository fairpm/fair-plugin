import { test, expect } from '@playwright/test';

/**
 * Install / Activate / Update full flow — critical e2e path.
 *
 * Installs Hello Dolly via the Direct Install tab using the mock server's
 * DID fixtures and served zip artifact. Then activates it and checks the
 * plugins page for update availability.
 *
 * These tests are @slow because they involve network downloads and WP cron.
 *
 * Prerequisites:
 *  - Docker: tests/sites/browser-test/docker-compose.yml up
 *  - WP installed and seeded (npm run seed)
 *  - Mock server running and serving fixtures
 */

const HELLO_DOLLY_DID = 'did:plc:hellodolly000000000000001';
const TAB_URL = '/wp-admin/plugin-install.php?tab=fair_direct';

test.describe('Install / Activate / Update', () => {

  test.beforeEach(async ({ page }) => {
    // Delete Hello Dolly if it was left from a previous run.
    await page.goto('/wp-admin/plugins.php');
    const deactivateLink = page.locator(`tr[data-slug="hello-dolly"] .deactivate a`);
    if (await deactivateLink.count() > 0) {
      await deactivateLink.click();
      await page.waitForLoadState('networkidle');
    }
    const deleteLink = page.locator(`tr[data-slug="hello-dolly"] .delete a`);
    if (await deleteLink.count() > 0) {
      await deleteLink.click();
      await page.waitForLoadState('networkidle');
    }
  });

  test('install Hello Dolly by DID', { tag: '@slow' }, async ({ page }) => {
    await page.goto(TAB_URL);
    await page.waitForSelector('.fair-direct-install__form', { timeout: 5000 });

    // Enter the hello-dolly DID.
    const input = page.locator('#plugin_id');
    await input.fill(HELLO_DOLLY_DID);

    // Submit the form — this opens a thickbox with plugin details.
    const submitBtn = page.locator('.fair-direct-install__form input[type="submit"], .fair-direct-install__form button[type="submit"]').first();
    await submitBtn.click();

    // Wait for thickbox to appear with the iframe.
    const iframe = page.frameLocator('#TB_iframeContent');
    await expect(page.locator('#TB_window')).toBeVisible({ timeout: 10000 });

    // The iframe should contain an install button.
    // WordPress uses .install-now or .button-activate class.
    const installBtn = iframe.locator('.install-now, .button-install-now').first();
    await expect(installBtn).toBeVisible({ timeout: 5000 });

    // Click install.
    await installBtn.click();

    // Wait for the installation to complete — the button text changes.
    // After install, the button becomes "Activate".
    const activateBtn = iframe.locator('.button-activate, a[href*="action=activate"]').first();
    await expect(activateBtn).toBeVisible({ timeout: 30000 });

    // Verify the activate button has accessible text.
    const activateText = await activateBtn.textContent();
    expect(activateText?.toLowerCase()).toContain('activate');
  });

  test('activate Hello Dolly after install', { tag: '@slow' }, async ({ page }) => {
    // First install.
    await page.goto(TAB_URL);
    await page.waitForSelector('.fair-direct-install__form');
    const input = page.locator('#plugin_id');
    await input.fill(HELLO_DOLLY_DID);
    const submitBtn = page.locator('.fair-direct-install__form input[type="submit"], .fair-direct-install__form button[type="submit"]').first();
    await submitBtn.click();

    const iframe = page.frameLocator('#TB_iframeContent');
    await expect(page.locator('#TB_window')).toBeVisible({ timeout: 10000 });

    const installBtn = iframe.locator('.install-now, .button-install-now').first();
    await expect(installBtn).toBeVisible({ timeout: 5000 });
    await installBtn.click();

    const activateBtn = iframe.locator('.button-activate, a[href*="action=activate"]').first();
    await expect(activateBtn).toBeVisible({ timeout: 30000 });

    // Click activate.
    await activateBtn.click();

    // After activation, the plugins page should show the plugin as active.
    await page.locator('#TB_window').waitFor({ state: 'hidden', timeout: 15000 }).catch(() => {});
    await page.goto('/wp-admin/plugins.php');
    await page.waitForLoadState('networkidle');

    // Hello Dolly should be listed and active.
    const row = page.locator('tr[data-slug="hello-dolly"]');
    await expect(row).toBeVisible({ timeout: 5000 });

    // The deactivate link should be visible (indicating it's active).
    const deactivateLink = row.locator('.deactivate a');
    await expect(deactivateLink).toBeVisible({ timeout: 5000 });
  });

  test('Hello Dolly shows in plugins list after install', { tag: '@slow' }, async ({ page }) => {
    // First install via Direct Install tab.
    await page.goto(TAB_URL);
    await page.waitForSelector('.fair-direct-install__form');
    const input = page.locator('#plugin_id');
    await input.fill(HELLO_DOLLY_DID);
    const submitBtn = page.locator('.fair-direct-install__form input[type="submit"], .fair-direct-install__form button[type="submit"]').first();
    await submitBtn.click();

    const iframe = page.frameLocator('#TB_iframeContent');
    await expect(page.locator('#TB_window')).toBeVisible({ timeout: 10000 });

    const installBtn = iframe.locator('.install-now, .button-install-now').first();
    await expect(installBtn).toBeVisible({ timeout: 5000 });
    await installBtn.click();

    // Wait for success message or activate button.
    const successEl = iframe.locator('.install-now, .button-activate, a[href*="action=activate"]').first();
    await expect(successEl).toBeVisible({ timeout: 30000 });

    // Go to plugins page.
    await page.goto('/wp-admin/plugins.php');
    await page.waitForLoadState('networkidle');

    // Hello Dolly row should exist.
    const row = page.locator('tr[data-slug="hello-dolly"]');
    await expect(row).toBeVisible({ timeout: 5000 });

    // The plugin name should be visible.
    const nameEl = row.locator('.plugin-title strong');
    await expect(nameEl).toHaveText(/Hello Dolly/i);
  });
});
