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

## Phase 4: DTO validation

- [ ] `MetadataDocumentTest.php` — all `from_data()` and `from_response()` cases
- [ ] `ReleaseDocumentTest.php` — all `from_data()` cases

## Phase 5: DID pipeline transient/HTTP functions (Priority B)

- [ ] `GetDidDocumentTest.php`
- [ ] `FetchPackageMetadataTest.php`
- [ ] `FetchMetadataDocTest.php`
- [ ] `GetLatestReleaseFromDidTest.php`
- [ ] `GetPackageDataTest.php`
- [ ] `AddPackageToReleaseCacheTest.php`
- [ ] `MaybeAddAcceptHeaderTest.php`
- [ ] `CacheUpdateErrorTest.php`
- [ ] `ClearUpdateErrorTest.php`
- [ ] `SearchByDidTest.php`
- [ ] `GetPluginInformationTest.php`

## Phase 6: Updater unit tests

- [ ] `UpdaterTest.php` — static registry, `should_run_on_current_page()`, plugin/theme registration/lookup
- [ ] `PluginPackageTest.php`
- [ ] `ThemePackageTest.php`
- [ ] `GetPackagesTest.php`
- [ ] `SignatureVerificationTest.php`
- [ ] `GetTrustedKeysTest.php`
- [ ] `RegisterPluginRowHooksTest.php`
- [ ] `DisplayPluginUpdateErrorTest.php`

## Phase 7: Supplementary module unit tests

- [ ] `Compatibility/PolyfillTest.php`
- [ ] `Avatars/*` tests
- [ ] `Salts/*` tests
- [ ] `Pings/*` tests
- [ ] `DefaultRepo/*` tests
- [ ] `VersionCheck/*` tests
- [ ] `Settings/*` tests
- [ ] `Upgrades/*` tests

## Phase 8: Integration harness

- [ ] Create `tests/sites/ephemeral/docker-compose.base.yml`
- [ ] Create `tests/sites/ephemeral/Dockerfile.wp`
- [ ] Create `tests/sites/ephemeral/integration/docker-compose.yml`
- [ ] Create `tests/sites/ephemeral/integration/wp-tests-config.php`
- [ ] Create `tests/sites/ephemeral/integration/seed.php`
- [ ] Create `tests/mock-server/` (Dockerfile, index.php, fixtures)
- [ ] Create `tests/integration/bootstrap.php`
- [ ] Create `tests/integration/phpunit.xml`
- [ ] Create `bin/run-integration.sh`

## Phase 9: Integration tests

- [ ] `InstallFlowTest.php`
- [ ] `UpdateTransientTest.php`
- [ ] `SignatureVerificationIntegrationTest.php`
- [ ] `WpCliCompatTest.php`

## Phase 10: HTTP test harness & tests

- [ ] Create `tests/http/bootstrap.php`
- [ ] Create `tests/http/phpunit.xml`
- [ ] `AdminAjaxTest.php`
- [ ] `PluginsApiTest.php`
- [ ] `UpdateTransientShapeTest.php`
- [ ] `IndexNowKeyTest.php`
- [ ] `SaltApiTest.php`
- [ ] `VersionCheckTest.php`
- [ ] `AvatarTest.php`
- [ ] `DefaultRepoTest.php`

## Phase 11: Browser test harness & tests

- [ ] Create `tests/browser/package.json`
- [ ] Create `tests/browser/playwright.config.ts`
- [ ] Create `tests/browser/global-setup.ts`
- [ ] `direct-install.spec.ts`
- [ ] `search-did.spec.ts`
- [ ] `install-activate-update.spec.ts`
- [ ] `avatar-upload.spec.ts`
- [ ] `update-error-row.spec.ts`

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
