# Static Sites ("Pet" Instances)

## Philosophy

Static sites are **persistent, manually-configured** WordPress installations that represent real-world deployment scenarios. Unlike ephemeral sites (Docker, CI, thrown away), these are standing pet instances that test FAIR against exotic configurations we've seen cause breakage in the wild.

They are NOT part of the automated CI pipeline. They're for manual QA, regression hunting, and reproducing user-reported issues.

## Directory convention

```
tests/sites/static/
├── bedrock/                  # Roots Bedrock (Composer-managed WP)
│   ├── README.md             # setup instructions, known gotchas
│   └── wp-tests-config.php   # DB creds (not committed, sample provided)
├── exotic-sap/               # SAP-hosted, custom directory layout
│   └── README.md
├── wp-in-subdir/             # WordPress in a subdirectory (e.g., /wp/)
│   └── README.md
├── mu-plugin-loaded/         # FAIR loaded as a must-use plugin
│   └── README.md
├── custom-content-dir/       # Non-standard WP_CONTENT_DIR and WP_CONTENT_URL
│   └── README.md
├── php-8.0-minimum/          # Bare PHP 8.0 with every extension missing
│   └── README.md
└── network-subsite/          # FAIR active on a multisite sub-site only
    └── README.md
```

## What goes in each README

Each scenario README answers:

1. **Why this scenario matters** — what real-world use case does it exercise? What has broken here before?
2. **Setup instructions** — step-by-step to reproduce the environment (Docker Compose, Bedrock install steps, wp-config.php overrides)
3. **If Docker-based** — a `docker-compose.yml` (or reference to one) and any custom dockerfiles
4. **Expected FAIR behavior** — what should work, what might not
5. **Known breakage** — has FAIR failed here? How did it manifest? Links to issues/PRs
6. **Test checklist** — manual test steps to verify FAIR functions correctly:
   - [ ] Plugin activates without fatal error
   - [ ] DID resolution works
   - [ ] Direct Install tab renders
   - [ ] Package install completes
   - [ ] Update detection fires
   - [ ] Signature verification passes
   - [ ] Avatars replace Gravatar
   - [ ] Salts are generated locally
   - [ ] IndexNow pings fire

## Example: Roots Bedrock

Bedrock moves WordPress core to `wp/`, plugins to `app/plugins/`, themes to `app/themes/`, and uses Composer for everything. This means:

- `WP_PLUGIN_DIR` is not the default
- Plugins aren't at `wp-content/plugins/` — `get_plugins()` and `wp_get_themes()` may behave differently
- `plugin_dir_url()` / `plugin_dir_path()` return unexpected paths
- FAIR's use of `WP_PLUGIN_DIR` and `get_file_data()` could resolve paths incorrectly

The Bedrock scenario README documents exactly how to set up a Bedrock site with FAIR installed as a Composer dependency (via a path repository pointing at the local checkout), and what to verify.

## Relationship to HTTP/browser tests

When HTTP or browser tests target a static site (via `FAIR_TEST_BASE_URL`), destructive tests (`@group destructive`) are automatically skipped. The static site's README lists which test groups are safe to run against it.

```bash
# Run non-destructive HTTP tests against the Bedrock pet
FAIR_TEST_BASE_URL=https://bedrock.local \
  FAIR_TEST_ADMIN_USER=admin \
  FAIR_TEST_ADMIN_PASS=password \
  composer run test:http -- --exclude-group destructive
```

## Static site lifecycle

- **Created** when a new exotic configuration is reported or anticipated
- **Maintained** as FAIR evolves — re-verify the checklist after major releases
- **Retired** when the configuration is no longer a support target (e.g., PHP version EOL) — moved to an `archive/` subdirectory with a note about when it was retired
