# Browser Tests

## Philosophy

- Playwright for real-browser end-to-end tests of critical UI paths.
- Base URL configurable via environment variable (same as HTTP tests).
- Pre-authenticated admin state avoids login overhead per test.
- Only a small set of UI-critical flows are tested here — most "e2e" coverage lives in HTTP tests.
- Same repo, isolated `package.json` so Playwright deps don't pollute the main plugin.

## Configuration

```typescript
// tests/browser/playwright.config.ts
import { defineConfig } from '@playwright/test';

export default defineConfig({
  testDir: './specs',
  timeout: 30000,
  retries: process.env.CI ? 2 : 0,
  use: {
    baseURL: process.env.FAIR_TEST_BASE_URL || 'http://localhost:8080',
    storageState: 'tests/browser/.auth/admin.json',
    screenshot: 'only-on-failure',
    trace: 'retain-on-failure',
  },
  projects: [
    {
      name: 'chromium',
      use: { browserName: 'chromium' },
    },
  ],
  webServer: process.env.FAIR_TEST_BASE_URL
    ? undefined  // No local server when targeting a pet instance
    : {
        command: 'docker compose -f tests/sites/ephemeral/browser-test/docker-compose.yml up -d --wait',
        port: 8080,
        reuseExistingServer: false,
      },
});
```

## Setup & auth bootstrap

`tests/browser/global-setup.ts`:

1. Launch headless browser
2. Navigate to `/wp-login.php`
3. Fill admin credentials
4. Save storage state to `tests/browser/.auth/admin.json`
5. This runs once before all tests (Playwright `globalSetup`)

```bash
# Run auth setup manually when credentials change
npx playwright test tests/browser/global-setup.ts
```

## Package isolation

`tests/browser/package.json`:

```json
{
  "private": true,
  "devDependencies": {
    "@playwright/test": "^1.52.0"
  },
  "scripts": {
    "test": "playwright test",
    "test:headed": "playwright test --headed",
    "test:slow": "playwright test --grep @slow",
    "auth": "playwright test global-setup.ts"
  }
}
```

The root `package.json` adds an npm script:

```json
{
  "scripts": {
    "test:browser": "cd tests/browser && npm run test",
    "test:browser:install": "cd tests/browser && npm install"
  }
}
```

## Browser test cases

### Direct Install tab

| Spec | Description |
|------|-------------|
| `direct-install.spec.ts: should render Direct Install tab` | Navigate to Add Plugins → Direct Install tab is visible |
| `direct-install.spec.ts: should open thickbox on valid DID submit` | Enter `did:plc:...` → submit → thickbox modal opens with plugin info |
| `direct-install.spec.ts: should show validation for invalid DID` | Enter `not-a-did` → submit → HTML5 validation prevents submit (pattern attribute) |
| `direct-install.spec.ts: should handle thickbox ARIA attributes` | Thickbox has `role=dialog`, `aria-label`, iframe has `title` |

### DID search

| Spec | Description |
|------|-------------|
| `search-did.spec.ts: should find plugin by DID search` | Search Add Plugins for `did:plc:...` → one result card shown |
| `search-did.spec.ts: should show install button for uninstalled DID plugin` | Result card has "Install Now" button |
| `search-did.spec.ts: should show update button for installed DID plugin` | When installed, result card shows update-appropriate button |
| `search-did.spec.ts: should show repository hostname` | Card description includes "Hosted on {host}" |

### Install → Activate → Update flow

| Spec | Description |
|------|-------------|
| `install-activate-update.spec.ts: @slow should install plugin from DID and activate` | Full flow: AJAX install, thickbox "Activate" button, plugin appears in installed list with DID-hash directory |
| `install-activate-update.spec.ts: @slow should detect and offer update` | Newer version → update notice appears in Plugins list |
| `install-activate-update.spec.ts: @slow should update plugin` | Click "Update Now" → success message → new version active |

### Avatar upload

| Spec | Description |
|------|-------------|
| `avatar-upload.spec.ts: should show upload button on profile` | Edit Profile → "Choose Profile Image" button visible |
| `avatar-upload.spec.ts: should upload and display custom avatar` | Upload image → preview shows → save → avatar displayed |
| `avatar-upload.spec.ts: should show remove button when avatar set` | After upload → "Remove Profile Image" button visible |
| `avatar-upload.spec.ts: should remove avatar` | Click remove → save → default avatar restored |

### Plugin row update error

| Spec | Description |
|------|-------------|
| `update-error-row.spec.ts: should display error row when update check failed` | Plugin with cached update error → error row visible below plugin in list |
| `update-error-row.spec.ts: should show retry time in error message` | Error row includes "Update checks paused for X time" |

## Ephemeral Docker setup

```
tests/sites/ephemeral/browser-test/
├── docker-compose.yml     # includes ../docker-compose.base.yml
├── wp-tests-config.php    # DB creds matching compose
└── seed.php               # creates test admin, pre-installs test plugin data
```

When running locally without `FAIR_TEST_BASE_URL`, Playwright spins the Docker stack and tears down after. When targeting a pet instance, the `webServer` config is skipped.

## Group annotations

| Annotation | Meaning | CI behavior |
|------------|---------|-------------|
| (none) | Fast browser tests (no actual plugin install) | Always runs |
| `@slow` | Installs plugins, waits for AJAX → full page reloads | Skipped in PR CI, run on main/RC |

In Playwright, these are implemented as test tags:

```typescript
test('@slow should install plugin from DID and activate', async ({ page }) => {
  // ...
});
```

CI filter: `npx playwright test --grep-invert @slow` (PR) or `npx playwright test` (main/RC).
