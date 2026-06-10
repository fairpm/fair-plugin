# Test Quality Audit — FAIR Plugin

**Date**: 2026-06-09  
**Branch**: `test_the_things`  
**By**: AI code review

---

## Summary

| Finding | Count |
|---------|-------|
| Vacuous / near-zero-value tests | 6 |
| Testing antipatterns | 4 |
| High-value expansions (security) | 5 |
| High-value expansions (general) | 6 |
| Code hard to test (design issues) | 3 |

---

## 1. Vacuous or Near-Zero-Value Tests

### 1.1 `tests/unit/tests/SampleTest.php` — **DELETE**

Tests that `true` is true, `false` is not true, and via data provider that `1`, `[]`, and `['populated']` are not `true`. This is template/guideware — zero coverage of any production code. Remove it and document the template format in `tests/unit/README.md` instead.

**Recommendation**: Delete the file.

### 1.2 `tests/unit/tests/DefaultRepoAndVersionCheckTest.php::VersionCheckConstantsTest` — **LOW VALUE**

Three tests validate that PHP constants (`RECOMMENDED_PHP`, `MINIMUM_PHP`) match a version regex and that `MINIMUM < RECOMMENDED`. These are string-format checks on values hardcoded in source — if the value is wrong (e.g. `'9.0'` instead of `'8.0'`), the test passes because `9.0` also matches `\d+\.\d+`. Only a structural regression (e.g. changing the constant to a non-version string like `'latest'`) would be caught.

**Recommendation**: Keep but merge into `GetDefaultRepoDomainTest` or a single `ConfigurationTest`. They have marginal value as structural guardrails.

### 1.3 `GetPackagesTest::test_should_have_plugins_key_when_packages_present` — **REDUNDANT**

Asserts `arrayHasKey('plugins', …)` **and** `arrayHasKey("plugins", …)` — the same assertion written twice with single vs double quotes. The second assertion is PHP-no-op. Then asserts `assertArrayNotHasKey('plugins', …)` when no FAIR plugins exist, which is already tested by `test_should_not_find_plugin_without_plugin_id`.

**Recommendation**: Remove the double-key assertion and the `assertArrayNotHasKey` (it's not the return structure's contract to always omit the `plugins` key — it's a side effect of how `foreach` accumulates).

### 1.4 `PickArtifactByLangTest::test_should_fire_filter_hook` — **TESTING WORDPRESS CORE**

Tests that `apply_filters(…)` fires registered callbacks. This is testing WordPress internals, not FAIR plugin logic. If `apply_filters` stops working, every plugin on the planet breaks simultaneously.

**Recommendation**: Remove. The companion test `test_filter_can_override_selection` already validates that the filter point works and is useful.

### 1.5 `DefaultRepoHttpTest::test_pre_http_request_filter_is_registered` — **TESTING WORDPRESS BOOTSTRAP**

Asserts `has_filter('pre_http_request', …)` > 0. Validates that the plugin bootstrapped correctly. If the bootstrap breaks, every other test in the suite fails too. Redundant at the HTTP layer — better tested via the unit bootstrap or a single "plugin loads" smoke test.

**Recommendation**: Remove from HTTP tests. Move to a single smoke test if needed.

### 1.6 `AvatarHttpTest::test_should_replace_gravatar_urls` / `test_should_not_replace_other_urls` — **DUPLICATE**

Both are direct copies of `ShouldReplaceUrlTest` in `tests/unit/tests/Avatars/AvatarsTest.php`. The HTTP tests should be testing `wp_remote_get` flows, not re-asserting unit-level filter behavior.

**Recommendation**: Remove the two duplicate assertions from AvatarHttpTest. Keep the HTTP test focused on `generate_default_avatar` and `get_avatar_alt`.

---

## 2. Testing Antipatterns

### 2.1 Reflection on private static arrays (`UpdaterTest::reset_registry`)

```php
$ref_plugins = new ReflectionProperty( Updater::class, 'plugins' );
$ref_plugins->setAccessible( true );
$ref_plugins->setValue( null, [] );
```

Tests tear down by reaching into private state with reflection. This couples tests to implementation details (the exact private property name). If someone renames `$plugins` to `$registry`, the tests break without any behavioral change.

**Better approach**: Add a `public static function reset(): void` to the Updater class (the method already exists at line 547 per the class source — is it used?). If it exists, the test should call `Updater::reset()` instead of using reflection.

**Recommendation**: Replace reflection with `Updater::reset()` if available, or add a `@visibleForTesting` reset method.

### 2.2 Mock-seeding entire HTTP pipeline for unit tests

`SearchByDidTest::seed_full_pipeline()` and `AddPackageToReleaseCacheTest::seed_pipeline()` mock the entire HTTP layer by seeding fake DID documents + `pre_http_request` filters with fake metadata responses inside unit tests. These are integration tests disguised as unit tests — they test the full pipeline (DID doc → service → HTTP fetch → metadata parse → release parse) inside a single test class.

**Why it matters**: These tests pass/fail based on whether the mock data structure exactly matches the production document format. If the metadata document schema changes, these "unit" tests break despite testing no production logic change. And they don't test any failure mode that a real integration test wouldn't catch better.

**Recommendation**: Move the success-path assertions to existing integration tests (`DidResolutionIntegrationTest`). Keep unit tests focused on edge cases the integration tests can't exercise (empty DID, non-DID search, pipeline failure propagation).

### 2.3 Testing constants as structural assertions

`VersionCheckConstantsTest` and the various `test_did_doc_has_verification_method` fixture-structure tests assert the *shape* of constants and fixtures rather than behavior. If a fixture's structure changes intentionally, the test breaks because it was testing the fixture, not the production code that consumes it.

**Recommendation**: Replace fixture-structure assertions with behavioral tests that consume the fixture and verify correct output.

### 2.4 Assertion on transient internals (`test_should_cache_result`)

```php
$this->assertSame( '', $cached, 'null result cached as empty string in WP transients.' );
```

Tests that WordPress internal behavior (`null` → `''` serialization in transients) works a specific way. If WP changes this, the test breaks even though the cache logic is correct.

**Recommendation**: Test behavior: "second call returns same value as first call (cached)" without asserting the internal storage format.

---

## 3. High-Value Expansions — Security Critical

These should be prioritized: signing key confusion, download tampering, and key re-encoding are the primary attack surfaces for a package manager.

### 3.1 `verify_signature_on_download()` — NO UNIT TESTS

**Status**: Zero unit tests. Only tested at integration layer via `SignatureVerificationIntegrationTest`, which requires a running Docker WP stack.

**What it does**: This is THE security-critical function — it hooks `upgrader_pre_download`, downloads a zip, and cryptographically verifies it against the DID document's public key before allowing WordPress to unzip it.

**Untested code paths**:
- `$has_run` infinite-loop guard (the static variable prevents re-entry for same DID+URL)
- Local file shortcut (`file_exists($package)` → return as-is without verification)
- `CACHE_DID_FOR_INSTALL` transient missing → returns reply unchanged
- `$upgrader->download_package()` returns WP_Error → error propagates
- WP-CLI success message when `is_wp_cli()` is true

**Testability**: The function is namespace-level (not in a class), depends on `$upgrader->download_package()`, `get_site_transient()`, and sodium functions. All can be mocked:
- Mock upgrader with a `download_package` that returns a known file
- Seed transients
- Create real zip + real signature

**Recommendation**: Add a `VerifySignatureOnDownloadTest` class with tests for:
- `$reply !== false` → returned unchanged
- Non-plugin/theme upgrader → returned unchanged
- Missing `CACHE_DID_FOR_INSTALL` → returned unchanged
- Missing release in `CACHE_RELEASE_PACKAGES` → returned unchanged
- Valid signature → returns path
- Tampered file → returns WP_Error
- `$has_run` guard → second call with same args returns original reply
- Local file → returned unchanged
- `download_package` returns WP_Error → propagates

### 3.2 Key confusion attack — `get_trusted_keys()` only checks first key match

The production code in `get_trusted_keys()` (inc/updater/namespace.php) filters a DID document's `verificationMethod` by `#fair-*` fragment, then recodes keys. But `verify_file_signature()` (WP core) tries ALL trusted keys. If a DID doc has `#fair-signing` key A and an attacker replaces the artifact's signature with one signed by key B (also `#fair-*` but attacker-controlled), the validation still passes because both keys are trusted.

**Current tests**: `GetTrustedKeysTest` tests filtering but not the security implication of returning ALL fair keys.

**Recommendation**: Add an integration test where a DID doc has two `#fair-*` keys but only one is authentic — verify that a signature from the *wrong* key also passes ( documenting this as intended behavior since WP's `verify_file_signature()` tries all trusted keys). If this is unintended, it's a bug.

### 3.3 Signature verification with cross-contaminated artifacts

`SignatureVerificationTest::test_signature_verifies_against_public_key()` tests that `sodium_crypto_sign_verify_detached()` works. It doesn't test the FAIR-specific encoding chain: DID doc → `didCodec::from_multibase_key()` → `base64_encode()` → WP `verify_file_signature()`. The integration test covers this via real WP, but there's no unit-level test for the recoding step.

**Recommendation**: Add `test_get_trusted_keys_recodes_to_base64()` — feeds a known multibase key through `get_trusted_keys()` and verifies the output is valid base64 of the raw 32-byte Ed25519 public key.

### 3.4 Replay attack — no nonce/challenge in signature

The signature is over archive content only. An attacker who obtains a valid `release-doc-signed.json` + `hello-dolly.zip` can re-serve those exact files for a different DID (if they control that DID's metadata endpoint). The signature verifies because the content hasn't changed.

**Current tests**: No test for binding between DID and artifact.

**Recommendation**: Not a test issue — this is a protocol design concern. Document it. If the FAIR protocol adds DID-binding to signatures later, add a test.

### 3.5 `upgrader_source_selection()` — ZERO TESTS

This function handles directory renaming when WordPress unzips a plugin. It strips the DID hash suffix to match the expected install directory. If broken, plugins install to wrong locations or the wrong directory is renamed.

**Untested**:
- Normal path: source `my-plugin-a1b2c3/` → destination `my-plugin/`
- Source without hash suffix → passed through unchanged
- `$upgrader` not Plugin_Upgrader/Theme_Upgrader → passed through

**Recommendation**: Unit test with mock upgrader and temp directories.

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

### 4.2 `plugin_api_details()` / `theme_api_details()` — NO UNIT TESTS

Provide the thickbox modal with plugin details. If broken, the "View details" popup is blank or crashes.

**Untested**:
- Non-DID slug → returned unchanged
- DID slug → fetches and returns plugin info object
- Failed fetch → returns original result

**Recommendation**: Add tests with `pre_http_request` mocking for DID-based slugs.

### 4.3 `Package::get_release()` / `Package::get_metadata()` — NO UNIT TESTS

These methods implement caching (memoization) and delegation to `Packages\fetch_package_metadata()` / `get_latest_release_from_did()`. The caching behavior is critical because it prevents redundant network calls.

**Untested**:
- First call fetches and caches
- Second call returns cached value
- Failed first call → returns WP_Error, does not cache
- Successful second call after failed first → fetches fresh

**Recommendation**: Unit test the memoization. Mock `Packages\fetch_package_metadata()` via filter.

### 4.4 `customize_theme_update_html()` / `append_theme_actions_content()` — NO TESTS

These provide the theme update UI in the Appearance screen. Untested entirely.

**Recommendation**: Browser test (already partially covered by theme listing in Playwright). Unit tests for the HTML generation would be brittle — better to expand the browser tests.

### 4.5 `handle_update_plugins_transient` error propagation

When `update_site_transient` encounters a package whose `get_release()` fails, it silently skips. The error is cached via `cache_update_error()`, but the transient is returned without the package. The user sees no update available. The error row is displayed separately via `display_plugin_update_error()`.

**Currently tested**: `display_plugin_update_error` output (unit) and error caching (unit). But **not** the end-to-end: error cached → plugin skipped in transient → error row shown.

**Recommendation**: End-to-end integration test: seed a DID doc + metadata that 404s, run `wp transient`, verify the error row appears and the plugin is NOT in `$transient->response`.

### 4.6 Browser tests are thin on the actual FAIR behavior

- `install-activate-update.spec.ts`: 3 @slow tests — install by DID, activate, verify in plugins list. Good structure but all @slow (not in regular CI). Needs mock server zip serving (already done). Not yet runnable because the thickbox DOM is untested.
- `avatar-upload.spec.ts`: 4 tests — profile rendering, avatar section, image loaded, display name field. None actually upload an avatar or verify local replacement. The hardest part (file upload + verifying the new avatar URL is local) is absent.
- `update-error-row.spec.ts`: 4 tests — plugins page renders, error row, FAIR plugin in list, no JS errors. The error row check is a no-op if no transients are seeded. Needs seed script to pre-set `fair_update_error_*` transients.

**Recommendation**: See section 5 (Deferred items) in implementation plan for actionable specifics.

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

### Immediate (this PR)

| # | Action | Effort |
|---|--------|--------|
| 1 | Delete `SampleTest.php` | Trivial |
| 2 | Remove redundant assertions from `GetPackagesTest::test_should_have_plugins_key_when_packages_present` | Trivial |
| 3 | Remove duplicate `should_replace_url` assertions from `AvatarHttpTest` | Trivial |
| 4 | Remove `PickArtifactByLangTest::test_should_fire_filter_hook` | Trivial |
| 5 | Remove `DefaultRepoHttpTest::test_pre_http_request_filter_is_registered` | Trivial |
| 6 | Replace reflection in `UpdaterTest::reset_registry` with `Updater::reset()` (check if it already exists) | Small |

### High Priority (next iteration)

| # | Action | Effort |
|---|--------|--------|
| 7 | Add `upgrader_source_selection` unit tests (hash-suffix stripping) | Medium |
| 8 | Add `verify_signature_on_download` unit tests (mocked upgrader) | Medium |
| 9 | Add `get_trusted_keys` base64 recoding unit test | Small |
| 10 | Add `Package::get_release()` memoization unit tests | Small |

### Medium Priority

| # | Action | Effort |
|---|--------|--------|
| 11 | Test `update_site_transient` via `handle_update_plugins_transient` with mocked packages | Medium |
| 12 | Test `plugin_api_details` with mocked DID pipeline | Medium |
| 13 | Add multi-key trust test (two fair keys, wrong one signs → should it pass?) | Small |
| 14 | Move pipeline-mock tests from unit to integration layer | Medium |

### Nice to Have

| # | Action |
|---|--------|
| 15 | Refactor `verify_signature_on_download` into testable + glue layers |
| 16 | Refactor `upgrader_source_selection` path computation into testable pure function |
| 17 | Make `update_site_transient` protected instead of private |
