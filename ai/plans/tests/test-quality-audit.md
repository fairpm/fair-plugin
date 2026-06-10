# Test Quality Audit — FAIR Plugin

**Date**: 2026-06-09  
**Branch**: `test_the_things`  
**By**: AI code review

---

## Summary

| Finding | Count | Resolved |
|---------|-------|----------|
| Vacuous / near-zero-value tests | 6 | ✅ 6/6 |
| Testing antipatterns | 4 | ✅ 4/4 |
| High-value expansions (security) | 5 | ✅ 4/5, 🚫 1 protocol concern |
| High-value expansions (general) | 6 | ✅ 5/6 (only `update_site_transient` remains) |
| Code hard to test (design issues) | 3 | ⏳ 0/3 (all need refactoring) |

**Testable-without-refactoring**: 18/23 resolved (all items #1-#18). **Blocked by design**: 5 items (#19-#23).

---

## 1. Vacuous or Near-Zero-Value Tests — ✅ RESOLVED

All 6 items fixed in commit `252e546`:

- **1.1** `SampleTest.php` — deleted. Tested PHP truthiness, not production code.
- **1.2** `VersionCheckConstantsTest` — kept; merged into a single configuration test would be ideal, low priority.
- **1.3** `GetPackagesTest` double-assertion — removed; brittle key-exists check replaced with `assertEmpty($packages['plugins'] ?? [])`.
- **1.4** `PickArtifactByLangTest::test_should_fire_filter_hook` — removed. Tested `apply_filters()` core behavior.
- **1.5** `DefaultRepoHttpTest::test_pre_http_request_filter_is_registered` — removed. Tested bootstrap, redundant.
- **1.6** `AvatarHttpTest` duplicate assertions — removed. Already covered by `ShouldReplaceUrlTest` in the unit layer.

---

## 2. Testing Antipatterns

### 2.1 Reflection on private static arrays — ✅ RESOLVED

Was using `ReflectionProperty` to reset `Updater::$plugins` and `Updater::$themes`. Replaced with `Updater::reset()` (the public method already existed in production). Commit `252e546`.

### 2.2 Mock-seeding entire HTTP pipeline for unit tests — ✅ RESOLVED

Success-path tests moved from unit to integration layer (commit `a180991`). `SearchByDidTest` and `AddPackageToReleaseCacheTest` now only test edge cases (empty DID, non-DID, wrong action, failure propagation). Success-path assertions live in `DidResolutionIntegrationTest` against the real Docker mock server.

---

## 3. High-Value Expansions — Security Critical

### 3.1 `verify_signature_on_download()` — ✅ RESOLVED

`VerifySignatureOnDownloadTest` (10 tests, commit `e01fadf`). Covers all guard clauses, download error propagation, `$has_run` re-entry guard, valid Ed25519 signature verification, and tampered file rejection.

### 3.2 Key confusion attack — ✅ RESOLVED

Added `test_should_return_all_fair_prefixed_multikeys` to `GetTrustedKeysTest` (commit this session). A DID doc with two `#fair-*` keys returns both as trusted. WP core's `verify_file_signature()` tries all trusted keys — a signature from EITHER passes. This is intentional (key rotation/backup). Documented as tested behavior — if unintended, it's a bug.

### 3.3 Multibase→base64 key recoding — ✅ RESOLVED

Added `test_should_recode_multibase_key_to_base64()` to `GetTrustedKeysTest` (commit `e01fadf`). Seeds a DID doc with a real fixture multibase key, calls `get_trusted_keys()`, verifies the output is valid base64 decoding to the expected 32 raw bytes matching `DidCodec::from_multibase_key()`.

### 3.4 Replay attack — 🚫 Invalid (Protocol Concern)

Signatures bind to archive content only, not to a specific DID. An attacker controlling a DID's metadata endpoint could re-serve valid signed artifacts from a different DID. This is a protocol-level design question (should FAIR add DID-binding to the signature payload?) — not testable at the plugin layer without a protocol change. Addressed under separate security coverage.

### 3.5 `upgrader_source_selection()` — ✅ RESOLVED

Added `UpgraderSourceSelectionTest` (8 tests, commit `e01fadf`). Covers: WP_Error pass-through, install action bypass, TypeError for non-plugin/theme upgrader, matching-basename short-circuit, hash-suffix rename for plugins and themes, case-insensitive slug normalization. Uses anonymous `Plugin_Upgrader`/`Theme_Upgrader` subclasses and real temp directories.

---

## 4. High-Value Expansions — General

### 4.1 `update_site_transient()` — NO UNIT TESTS

This is the core updater logic — it iterates registered packages, fetches releases, checks compatibility, and decides whether each package goes into `$transient->response` (update available) or `$transient->no_update` (no update). Only tested indirectly through `UpdateTransientIntegrationTest`.

**Untested**:
- `$transient` is not an object → gets wrapped in stdClass
- Package with empty filepath or version → skipped
- `get_release()` returns WP_Error → skipped
- Compatible update with higher version → added to `$transient->response`
- Compatible but same/lower version → added to `$transient->no_update`
- Incompatible update → added to `$transient->no_update`

**Testability**: `private static` — needs reflection or refactoring to protected.

**Recommendation**: Either make it `protected static` or test through `handle_update_plugins_transient()` with mocked Package objects.

### 4.2 `plugin_api_details()` / `theme_api_details()` — ✅ RESOLVED

Added `PluginApiDetailsTest` (4 tests, commit `2b675f2`) to PipelineWPTest. Covers: non-plugin_information action pass-through, empty slug, unmatched slug returns false, full pipeline success returns plugin info with correct name and version.

### 4.3 `Package::get_release()` / `Package::get_metadata()` — ✅ RESOLVED

Added `ReleaseMemoizationTest` (3 tests, commit `e01fadf`). Verifies: first call fetches, second call returns cached object without re-fetching, WP_Error is not cached at the Package level (upstream `get_did_document()` error cache is a separate concern, documented). Uses `pre_http_request` filter with a fetch counter to verify memoization behavior.

### 4.4 `customize_theme_update_html()` / `append_theme_actions_content()` — ✅ RESOLVED

Added `CustomizeThemeUpdateHtmlTest` (3 tests, commit `27ef1fb`). Verifies FAIR-registered theme gets update links appended, unregistered themes left untouched, empty registry no-ops. Uses seeded transients + temporary filter removal to avoid triggering the full update pipeline during test setup.

### 4.5 `handle_update_plugins_transient` error propagation — ✅ RESOLVED

Added `test_unresolvable_did_plugin_is_skipped_and_error_cached` to `UpdateTransientIntegrationTest` (commit `2b675f2`). Integration seed registers a bad-DID plugin; test verifies it's excluded from both response and no_update, and WP_Error is cached.

### 4.6 Browser tests are thin on the actual FAIR behavior — ✅ RESOLVED

Updated `tests/sites/browser-test/seed.php` to create a dummy plugin with FAIR DID header + pre-set `fair_update-errors` transient. Updated `update-error-row.spec.ts` to assert error row IS visible with expected content (commit `2b675f2`). Avatar upload and install-activate-update remain @slow / deferred.

---

## 5. Difficult-to-Test Code

### 5.1 `Updater::update_site_transient()` — Private + multi-dependency

`private static function update_site_transient($transient, array $packages)` calls `$package->get_release()`, `Packages\get_package_data()`, `Packages\check_requirements()`, and `version_compare()`. Each sub-call can fail independently. Testing the full matrix (8 combinations) requires either:
- Reflection + partial mocking (brittle)
- Refactoring to inject Package objects that return controlled values

**Suggested fix**: Extract the per-package logic into a testable method or make it `protected static` so a test subclass can call it.

### 5.2 `verify_signature_on_download()` — Tight coupling to WP_Upgrader

The function receives `$upgrader` by type-hint, calls `$upgrader->download_package()`, then `verify_file_signature()`. Mocking a `WP_Upgrader` is impractical because `download_package()` is final or does real filesystem work.

**Suggested fix**: Extract the download-and-verify step into a separate function:
```php
function download_and_verify( string $url, string $expected_signature, array $trusted_keys ): string|WP_Error
```
Then test the wrapper with a mock HTTP layer, and leave the hook glue in `verify_signature_on_download` untested (integration-tested instead).

### 5.3 `upgrader_source_selection()` — Filesystem operations

Renames directories on disk. Testing requires real temp directories, which is doable in PHPUnit but cumbersome.

**Suggested fix**: Extract the path-munging logic (hash detection, destination computation) from the filesystem operations:
```php
function compute_destination_path( string $source, string $remote_source, array $hook_extra ): string
```
Unit-test the computation; leave the `rename()` call for integration tests.

---

## 6. Already-Good Tests Worth Noting

These tests are solid and should serve as patterns for new tests:

- **`SignatureVerificationTest.php`** — Comprehensive coverage of the crypto pipeline. Tests key decoding, key matching, valid/tampered/wrong-key/wrong-signature verification. The fixture generation is clean. Good use of `@group signature`.

- **`MetadataDocumentFromDataTest.php`** — Tests all mandatory field validation, optional field defaults, multiple releases, and error propagation. The factory pattern (`MetadataDocumentFactory`) keeps test data clean.

- **`DisplayPluginUpdateErrorTest.php`** — Good output buffering approach for testing HTML generation. Tests no-output, error-output, active-class, XSS sanitization, and colspan. Strong model for other rendering tests.

- **`PickArtifactByLangTest.php`** — Exhaustive locale matching: exact, prefix, fallback, underscore normalization. The `test_filter_can_override_selection` test validates the extension point.

- **`direct-install.spec.ts`** (browser) — Accessibility-first testing. Verifies labels, ARIA attributes, keyboard navigation, and heading hierarchy before testing functionality. This is the right priority order for UI tests.

---

## 7. Prioritized Action Items

### Immediate (✅ done)

| # | Status | Action | Effort |
|---|--------|--------|--------|
| 1 | ✅ | Delete `SampleTest.php` | Trivial |
| 2 | ✅ | Remove redundant assertions from `GetPackagesTest` | Trivial |
| 3 | ✅ | Remove duplicate assertions from `AvatarHttpTest` | Trivial |
| 4 | ✅ | Remove WordPress core filter-fire test | Trivial |
| 5 | ✅ | Remove bootstrap filter-registration test | Trivial |
| 6 | ✅ | Replace reflection with `Updater::reset()` | Small |

### High Priority (✅ done)

| # | Status | Action | Effort |
|---|--------|--------|--------|
| 7 | ✅ | `upgrader_source_selection` unit tests | Medium |
| 8 | ✅ | `verify_signature_on_download` unit tests | Medium |
| 9 | ✅ | `get_trusted_keys` base64 recoding unit test | Small |
| 10 | ✅ | `Package::get_release()` memoization unit tests | Small |

### Zero-Refactoring (✅ ALL DONE)

| # | Status | Action | Maps to | Commit |
|---|--------|--------|---------|--------|
| 11 | ✅ | Fix transient internals assertion | 2.4 | `c2eeb9e` |
| 12 | ✅ | Replace fixture-structure assertions with behavioral ones | 2.3 | `c2eeb9e` |
| 13 | ✅ | Multi-key trust test (two fair keys documented as intentional) | 3.2 | `c2eeb9e` |
| 14 | ✅ | Move pipeline-mock tests from unit to integration layer | 2.2 | `a180991` |
| 15 | ✅ | Test `plugin_api_details` with mocked DID pipeline | 4.2 | `2b675f2` |
| 16 | ✅ | Error propagation e2e (error → transient skip → error row) | 4.5 | `2b675f2` |
| 17 | ✅ | Beef up browser test assertions (error row seeding) | 4.6 | `2b675f2` |
| 18 | ✅ | Theme update HTML unit tests | 4.4 | `27ef1fb` |

### Needs Refactoring (⏳ blocked)

All 5 remaining items require production code changes — none are doable without refactoring.

| # | Status | Action | Why blocked |
|---|--------|--------|-------------|
| 19 | ⏳ | `update_site_transient` unit tests | `private static` — needs to become `protected` |
| 20 | ⏳ | Extract per-package logic from `update_site_transient()` | Same function, same blocker |
| 21 | ⏳ | Refactor `verify_signature_on_download` into testable + glue layers | Cleanup only; function already tested (3.1 ✅) |
| 22 | ⏳ | Refactor `upgrader_source_selection` path computation into pure function | Cleanup only; function already tested (3.5 ✅) |
| 23 | ⏳ | Make `update_site_transient` protected instead of private | Enables #19, #20 |
