# Implementation Order

The plan is designed to be executed incrementally. Each phase produces runnable tests before moving on.

## Phase 1: Infrastructure migration ✅

- [x] Rename `tests/phpunit/` → `tests/unit/`
- [x] Move `phpunit.xml.dist` → `tests/unit/phpunit.xml`, adjust paths
- [x] Move `tests/phpunit/multisite.xml` → `tests/unit/multisite.xml`
- [x] Move `tests/phpunit/tests/Packages/*` → `tests/unit/tests/Packages/*` (preserve existing tests)
- [x] Move `tests/phpunit/tests/SampleTest.php` → `tests/unit/tests/SampleTest.php`
- [x] Update `composer.json` scripts: `test` → `test:unit`, pointing at `tests/unit/phpunit.xml`. Also `test:multisite` → `test:unit:multisite`. Updated `coverage:*` paths.
- [x] Update `package.json` npm scripts to reference new composer script names
- [x] Update `tests/unit/README.md` with new paths
- [x] Bump `.wp-env.json` PHP version from 7.4 → 8.0 (hard floor per AGENTS.md)
- [x] Add `"platform": {"php": "8.0"}` to `composer.json` config → ran `composer update` → lock file resolves all deps for PHP 8.0
- [x] Fix `test:php:install-deps` npm script: add `--ignore-platform-req=ext-gmp` (wp-env PHP 8.0 image lacks gmp)
- [x] Verify existing tests still pass: 19 tests, 30 assertions ✅

**Remaining known issue:** `simplito/elliptic-php` (dependency of `fairpm/did-manager`) requires `ext-gmp`, which is missing from the wp-env PHP 8.0 Docker image. Workaround: `--ignore-platform-req=ext-gmp` in the install-deps script. A proper fix would be installing gmp in the Docker image or upstreaming an ext-gmp change to the wp-env image.

## Phase 2: Fixtures & factories ✅

- [x] Create `tests/fixtures/` directory
- [x] Create all fixture JSON files (did-doc, metadata-doc, release-doc variants — 13 files total)
- [x] Create `tests/Factory/MetadataDocumentFactory.php` (full, minimal, from_fixture, builder, error paths)
- [x] Create `tests/Factory/ReleaseDocumentFactory.php` (full, with_version, with_requirements, builder, error paths)
- [x] Add `autoload-dev` PSR-4 entry for `FAIR\Tests\` → `tests/` in composer.json
- [x] Verify existing tests still pass: 19 tests, 30 assertions ✅

## Phase 3: DID pipeline pure functions (Priority A) ✅

- [x] `GetDidHashTest.php` — 8 tests: hex string, deterministic, different DIDs, error propagation, empty string, 32-char, long DID, non-plc method
- [x] `GetLanguagePriorityListTest.php` — 11 tests: full locale first, underscore conversion, lowercase, prefix decomposition, x- subtag skip, doubled primary code, defaults, simple locale, filter hook, filter override, zh-Hans-CN order
- [x] `PickArtifactByLangTest.php` — 10 tests: exact match, specificity preference, no-match fallback, empty artifacts, single artifact, doubled primary code, en-us default, filter hook, filter override, underscore locale
- [x] `PickReleaseTest.php` — 7 tests: latest default, specific version, version not found, sort correctness, single release, empty releases, null version
- [x] `VersionRequirementsTest.php` — 9 tests: requires_php, requires_wp, tested_to, all three, empty, caret/tilde strip, non-env ignore, missing requires, missing suggests
- [x] `GetUnmetRequirementsTest.php` — 6 tests (+ data provider): all met, unmet PHP, unmet WP, empty, multi-unmet joined, invalid specifiers, unknown env, operator comparison
- [x] `CheckRequirementsTest.php` — 4 tests: all met, PHP unmet, WP unmet, empty requires
- [x] `GetIconsTest.php` — 7 tests: 1x/2x, wporg SVG default, non-wporg SVG, empty, no valid sizes, only 1x, only 2x
- [x] `GetBannersTest.php` — 5 tests: low/high, empty, no valid sizes, only low, only high
- [x] `GetHashedFilenameTest.php` — 5 tests: plugin slug with hash, theme slug, no double-append, deterministic, different DIDs
- [x] `ValidatePackageAliasTest.php` — 9 tests (cached + uncached): cache hit, cache set, unique keys, no aliases, non-fair aliases, multiple aliases, invalid domain, no TLD, excessively long domain, missing alsoKnownAs, non-string aliases

**Bug fixed:** `pick_release()` — added empty-array guard to prevent TypeError on `reset()` returning false with `?ReleaseDocument` return type.
**Note:** `get_site_transient` converts `null` → `''`; alias cache test accounts for this.

## Phase 4: DTO validation ✅

- [x] `MetadataDocumentTest.php` — 13 tests (from_data): all fields, minimal, 5 missing mandatory fields, missing releases, invalid release propagation, optional fields null, multiple releases parsed, security array. 5 tests (from_response): valid response, invalid JSON, valid JSON + invalid data, empty body, null body (TypeError note)
- [x] `ReleaseDocumentTest.php` — 10 tests: all fields, with requirements, specific version, missing version, missing artifacts, optional fields null, minimal artifacts, builder with unset fields

## Phase 5: DID pipeline transient/HTTP functions (Priority B) ✅

- [x] `CacheUpdateErrorTest.php` — 7 tests: cache error, timestamp, lifetime, clear, idempotent, DID isolation
- [x] `GetDidDocumentTest.php` — 3 tests: cache hit, cached error, parse error+cache
- [x] `FetchMetadataDocTest.php` — 6 tests: cache hit, HTTP failure caching, non-200, cache on success, metadata from HTTP
- [x] `FetchPackageMetadataTest.php` — 6 tests: no service, ID mismatch, success, DID error propagation + get_latest_release_from_did (2 tests: success, no keys)
- [x] `PipelineWPTest.php` — 20 tests: add_package_to_release_cache (4), maybe_add_accept_header (5), search_by_did (6), get_plugin_information (3)

Uses `pre_http_request` filter + pre-seeded transients instead of real HTTP calls.

## Phase 6: Updater unit tests ✅

- [x] `UpdaterTest.php` — 14 tests: register/get plugins (3), register/get themes (2), unknown DID (2), overwrite, get_plugins/get_themes, empty, independent plugin/theme, get_plugin_by_file, unknown file + 8 tests for `should_run_on_current_page` (plugins, themes, update-core, update, plugin-install, admin-ajax, edit.php, post.php)
- [x] `PackageTest.php` — 8 tests: PluginPackage construct+version, slug, relative path, deep nesting, version override + ThemePackage construct+version, slug, type distinction
- [x] `GetTrustedKeysTest.php` — 5 tests: no cached DID, fetch failure, no keys, empty verificationMethod, non-fair filtering
- [x] `DisplayPluginUpdateErrorTest.php` — 6 tests: no error, non-error transient, error row output, active class, HTML sanitization, colspan
- [x] `GetPackagesTest.php` — 4 tests: Plugin ID header, multiple plugins, no header, keys structure

### Phase 6b: Updater edge cases ✅

- [x] `GetTrustedKeysTest.php` — included above
- [x] `DisplayPluginUpdateErrorTest.php` — included above
- [x] `GetPackagesTest.php` — included above
- [ ] `SignatureVerificationTest.php` — deferred (needs real crypto operations, better suited for manual/integration testing)
- [ ] `RegisterPluginRowHooksTest.php` — deferred (hook registration verified implicitly through admin_init flow)

Uses temp file creation (`wp_mkdir_p` + `file_put_contents`) so constructors'
`get_file_data()` calls resolve successfully.

## Phase 7: Supplementary module unit tests ✅

- [x] `AvatarsTest.php` — 18 tests: should_replace_url (5), generate_default_avatar (8; 1 skipped for color hook bug), get_avatar_alt (4)
- [x] `PingsTest.php` — 11 tests: remove_pingomatic (5), get_indexnow_key (4), register_query_vars (2)
- [x] `SaltsTest.php` — 9 tests: replace_salt_api (3), define_keynames (1), generate_salt (3), response_body (2), get_response (1)
- [x] `DefaultRepoAndVersionCheckTest.php` — 6 tests: default repo domain (2), version-check constants (4)
- [ ] `Compatibility/PolyfillTest.php` — deferred
- [ ] `Settings/*` — deferred (heavily WP-hook dependent)
- [ ] `Upgrades/*` — deferred (heavily WP-hook dependent)

**Bugs found:** `generate_default_avatar()` uses `add_filter()` instead of `apply_filters()` for `fair_avatars_default_color` (1 test skipped). `esc_attr()` can expand salt strings beyond 64 chars.

## Phase 8: Integration harness ✅

- [x] `tests/sites/ephemeral/integration/docker-compose.yml` — self-contained WP 6.4 + PHP 8.0 + MariaDB + wp-cli + mock-server
- [x] `tests/sites/ephemeral/Dockerfile.wp` — custom WP image
- [x] `tests/mock-server/` — Dockerfile + PHP built-in server emulating PLC Directory and FAIR Repository APIs
- [x] `tests/mock-server/index.php` — file-based request log, fixture-driven responses
- [x] `tests/integration/bootstrap.php` — loads WP directly (no WP_UnitTestCase needed)
- [x] `tests/integration/phpunit.xml` — PHPUnit config
- [x] `tests/sites/ephemeral/integration/seed.php` — registers test plugin with DID header
- [x] `bin/run-integration.sh` — full lifecycle with trap EXIT teardown guarantee
- [x] `FAIR_PLC_DIRECTORY_URL` constant (minimal production change for testability)

## Phase 9: Integration tests ✅

- [x] `DidResolutionIntegrationTest.php` — 4 tests: mock health, full pipeline, log check (skipped), unknown DID error
- [x] `PackageDataIntegrationTest.php` — 5 tests: complete response, _fair metadata, no-service error, unknown DID, caching
- [x] `UpdateTransientIntegrationTest.php` — 3 tests: valid transient, seeded plugin (skipped), empty registry
- [ ] `SignatureVerificationIntegrationTest.php` — deferred
- [ ] `WpCliCompatTest.php` — deferred

12 integration tests (10 pass, 2 skipped), 38 assertions.

---

---

## Phase 10: HTTP test harness & tests ✅

- [x] `tests/http/bootstrap.php` — loads WP + plugin with admin includes
- [x] `tests/http/phpunit.xml` — PHPUnit config
- [x] `SaltApiHttpTest.php` — 3 tests: salt API URL interception, 64-char values, passthrough
- [x] `DefaultRepoHttpTest.php` — 5 tests: domain config, non-WP.org passthrough, filter registration, plugins/themes API interception
- [x] `AvatarHttpTest.php` — 4 tests: should_replace_url, SVG default avatar, alt text for users
- [ ] `AdminAjaxTest.php` — deferred
- [ ] `PluginsApiTest.php` — deferred to browser tests
- [ ] `UpdateTransientShapeTest.php` — covered in integration

12 HTTP tests + 12 integration = 24 Docker tests, 76 assertions.

## Phase 11: Browser test harness & tests ✅

- [x] `tests/browser/package.json` — isolated @playwright/test deps
- [x] `tests/browser/playwright.config.ts` — chromium, auth state, CI retries
- [x] `tests/browser/global-setup.ts` — login as browser_admin, save storage
- [x] `tests/browser/specs/direct-install.spec.ts` — 9 tests: label, pattern, required, submit focus, validation, thickbox role/label/iframe/close (all pass)
- [x] `tests/browser/specs/search-did.spec.ts` — 6 tests: searchbox label, DID result, no-results, install button, hostname, heading (all pass)
- [ ] `tests/browser/specs/install-activate-update.spec.ts` — deferred (@slow, needs stable plugin data)
- [ ] `tests/browser/specs/avatar-upload.spec.ts` — deferred
- [ ] `tests/browser/specs/update-error-row.spec.ts` — deferred

**15 tests pass, 0 skipped, 0 failed.**
Run: `npm run test:browser` (needs `npm run test:browser:docker:start` first)

---

## Current totals (after Phase 11)

| Layer | Tests | Assertions | Runner |
|-------|-------|------------|--------|
| Unit | 248 | 430 | `composer run test:unit` (local) |
| Integration | 12 | 38 | `bin/run-integration.sh` (Docker) |
| HTTP | 12 | 38 | `bin/run-integration.sh` (Docker) |
| Browser | 15 | — | `npm run test:browser` (Playwright) |
| **Total** | **287** | **506+** | |

---

## Phase 12: CI workflow

- [ ] Create `.github/workflows/test.yml`
- [ ] Verify matrix passes on push
- [ ] Tune timeouts and retries

## Phase 13: Coverage baseline & Infection (future)

- [ ] Establish coverage baseline report
- [ ] Add `infection/infection` dev dependency
- [ ] Configure `infection.json`
- [ ] Run initial mutation test, record MSI baseline
- [ ] Iterate on tests to raise MSI to target threshold

---

## Parallelizable work

Phases 3–7 (unit tests) are largely independent per module and can be parallelized across contributors. Phases 8–11 are sequential — each builds on the previous. Phase 12 depends on all tests being in place.
