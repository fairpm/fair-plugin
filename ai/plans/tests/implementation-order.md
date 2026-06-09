# Implementation Order

The plan is designed to be executed incrementally. Each phase produces runnable tests before moving on.

## Phase 1: Infrastructure migration

- [ ] Rename `tests/phpunit/` → `tests/unit/`
- [ ] Move `phpunit.xml.dist` → `tests/unit/phpunit.xml`, adjust paths
- [ ] Move `tests/phpunit/multisite.xml` → `tests/unit/multisite.xml`
- [ ] Move `tests/phpunit/tests/Packages/*` → `tests/unit/tests/Packages/*` (preserve existing tests)
- [ ] Move `tests/phpunit/tests/SampleTest.php` → `tests/unit/tests/SampleTest.php`
- [ ] Update `composer.json` scripts: `test` → `test:unit`, pointing at `tests/unit/phpunit.xml`
- [ ] Verify existing tests still pass: `composer run test:unit`

## Phase 2: Fixtures & factories

- [ ] Create `tests/fixtures/` directory
- [ ] Create all fixture JSON files (did-doc, metadata-doc, release-doc variants)
- [ ] Create `tests/factory/class-metadata-document-factory.php`
- [ ] Create `tests/factory/class-release-document-factory.php`

## Phase 3: DID pipeline pure functions (Priority A)

Targeting `inc/packages/namespace.php` functions that don't depend on WordPress APIs.

- [ ] `GetDidHashTest.php`
- [ ] `GetLanguagePriorityListTest.php`
- [ ] `PickArtifactByLangTest.php`
- [ ] `PickReleaseTest.php`
- [ ] `VersionRequirementsTest.php`
- [ ] `GetUnmetRequirementsTest.php`
- [ ] `CheckRequirementsTest.php`
- [ ] `GetIconsTest.php`
- [ ] `GetBannersTest.php`
- [ ] `GetHashedFilenameTest.php`
- [ ] `ValidatePackageAliasTest.php`
- [ ] `FetchAndValidatePackageAliasTest.php`

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
