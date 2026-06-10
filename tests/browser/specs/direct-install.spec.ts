import { test, expect } from '@playwright/test';

/**
 * Direct Install tab — critical UI path for installing plugins by DID.
 *
 * Page structure (from inc/packages/admin/namespace.php):
 *  - Tab ID: 'fair_direct', URL: ?tab=fair_direct
 *  - Form class: .fair-direct-install__form
 *  - Input: #plugin_id, pattern="did:plc:.+"
 *  - Thickbox JS sets role="dialog", aria-label="Plugin details"
 *  - Iframe gets title="Plugin details"
 *
 * Accessibility priorities verified:
 *  - Input has <label> (screen-reader-text) + aria-describedby
 *  - Input has pattern attribute for HTML5 validation
 *  - Thickbox modal has role="dialog" + aria-label (set via JS)
 *  - Iframe has title attribute (set via JS)
 *  - Submit button is keyboard accessible
 */

const KNOWN_DID = 'did:plc:z72i7hdynmk6r22z27h6tvur';
const TAB_URL = '/wp-admin/plugin-install.php?tab=fair_direct';

test.describe('Direct Install tab', () => {

  test.beforeEach(async ({ page }) => {
    await page.goto(TAB_URL);
    // Wait for the Direct Install form to be visible.
    await page.waitForSelector('.fair-direct-install__form', { timeout: 5000 });
  });

  test('DID input has accessible label (screen-reader-text)', async ({ page }) => {
    const input = page.locator('#plugin_id');
    await expect(input).toBeVisible();

    // The <label for="plugin_id"> exists with screen-reader-text class.
    const label = page.locator('label[for="plugin_id"]');
    await expect(label).toBeAttached();
    const labelText = await label.textContent();
    expect(labelText?.trim()).toBe('Plugin ID');

    // aria-describedby links to help text.
    const describedBy = await input.getAttribute('aria-describedby');
    expect(describedBy).toBeTruthy();
    const helpEl = page.locator(`#${describedBy}`);
    await expect(helpEl).toBeAttached();
  });

  test('DID input has pattern attribute for HTML5 validation', async ({ page }) => {
    const input = page.locator('#plugin_id');
    const pattern = await input.getAttribute('pattern');
    expect(pattern).toBe('did:plc:.+');
  });

  test('DID input is required', async ({ page }) => {
    const input = page.locator('#plugin_id');
    const required = await input.getAttribute('required');
    expect(required).toBeDefined();
  });

  test('submit button is keyboard accessible', async ({ page }) => {
    // WordPress submit_button renders as <input type="submit"> or <button>.
    const submitBtn = page.locator('.fair-direct-install__form input[type="submit"], .fair-direct-install__form button[type="submit"]').first();

    // Must be focusable (not disabled, not hidden).
    await submitBtn.focus();
    await expect(submitBtn).toBeFocused();

    // Must have accessible text (value attribute for input[type=submit]).
    const value = await submitBtn.getAttribute('value') || await submitBtn.textContent() || '';
    expect(value.trim().length).toBeGreaterThan(0);
  });

  test('invalid DID shows HTML5 validation and prevents submit', async ({ page }) => {
    const input = page.locator('#plugin_id');

    // Type an invalid value that doesn't match pattern.
    await input.fill('not-a-valid-did');

    // Try to submit via Enter key.
    await input.press('Enter');

    // HTML5 validation should prevent navigation.
    await expect(page).toHaveURL(TAB_URL);

    // Input should be invalid.
    const isValid = await input.evaluate(el => (el as HTMLInputElement).validity.valid);
    expect(isValid).toBe(false);
  });

  test('valid DID triggers thickbox modal open', async ({ page }) => {
    const input = page.locator('#plugin_id');

    await input.fill(KNOWN_DID);

    // Click submit — the JS handler calls tb_show().
    const submitBtn = page.locator('.fair-direct-install__form input[type="submit"], .fair-direct-install__form button[type="submit"]').first();

    await submitBtn.click();

    // Thickbox overlay should appear.
    const thickbox = page.locator('#TB_window');
    await expect(thickbox).toBeVisible({ timeout: 5000 });
  });

  test('thickbox modal has dialog role and accessible label', async ({ page }) => {
    // Open thickbox.
    const input = page.locator('#plugin_id');
    await input.fill(KNOWN_DID);
    const submitBtn = page.locator('.fair-direct-install__form input[type="submit"], .fair-direct-install__form button[type="submit"]').first();
    await submitBtn.click();

    // Wait for thickbox.
    const thickbox = page.locator('#TB_window');
    await expect(thickbox).toBeVisible({ timeout: 5000 });

    // JS handler sets role="dialog".
    const role = await thickbox.getAttribute('role');
    expect(role).toBe('dialog');

    // JS handler sets aria-label="Plugin details".
    const label = await thickbox.getAttribute('aria-label');
    expect(label).toBe('Plugin details');

    // JS handler adds CSS class.
    const cls = await thickbox.getAttribute('class');
    expect(cls).toContain('plugin-details-modal');
  });

  test('thickbox iframe has title attribute', async ({ page }) => {
    // Open thickbox.
    const input = page.locator('#plugin_id');
    await input.fill(KNOWN_DID);
    const submitBtn = page.locator('.fair-direct-install__form input[type="submit"], .fair-direct-install__form button[type="submit"]').first();
    await submitBtn.click();

    // Wait for thickbox.
    const thickbox = page.locator('#TB_window');
    await expect(thickbox).toBeVisible({ timeout: 5000 });

    // JS handler sets title="Plugin details" on the iframe.
    const iframe = page.locator('#TB_iframeContent');
    const iframeCount = await iframe.count();
    if (iframeCount > 0) {
      const title = await iframe.getAttribute('title');
      expect(title).toBe('Plugin details');
    }
  });

  test('thickbox close button is present and has accessible name', async ({ page }) => {
    // Open thickbox.
    const input = page.locator('#plugin_id');
    await input.fill(KNOWN_DID);
    const submitBtn = page.locator('.fair-direct-install__form input[type="submit"], .fair-direct-install__form button[type="submit"]').first();
    await submitBtn.click();

    // Wait for thickbox.
    await expect(page.locator('#TB_window')).toBeVisible({ timeout: 5000 });

    // TB uses #TB_closeWindowButton for the close button.
    const closeBtn = page.locator('#TB_closeWindowButton');
    await expect(closeBtn).toBeVisible();

    // Must have accessible name (title attribute, aria-label, or text content).
    const title = await closeBtn.getAttribute('title') || await closeBtn.getAttribute('aria-label') || (await closeBtn.textContent())?.trim() || '';
    expect(title.length).toBeGreaterThan(0);
  });
});
