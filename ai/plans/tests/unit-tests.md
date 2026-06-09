# Unit Tests

## Philosophy

- No Docker required — unit tests use the WordPress test framework with a local MySQL database.
- Run via `composer run test:unit` (mapped from current `composer run test`).
- PHP version matrix: 8.0 through 8.4 (minimum floor per AGENTS.md).
- `WP_UnitTestCase` is the base class — provides WP factory, transient mocking, filter/action hooks.
- **Exhaustive edge cases** for the DID pipeline: null bytes, Unicode DIDs, malformed JSON in every possible field position, transient race conditions, WP_Error propagation at every hop.

## Fixture files

JSON files under `tests/fixtures/` that mirror real PLC directory and FAIR repo API responses:

| Fixture | Use |
|---------|-----|
| `did-doc-valid.json` | Complete DID document with services, verificationMethods, alsoKnownAs |
| `did-doc-no-keys.json` | DID doc missing `verificationMethod` — triggers WP_Error for install |
| `did-doc-no-services.json` | DID doc missing fair repo service — triggers WP_Error for metadata fetch |
| `did-doc-alias-valid.json` | Valid `fair://` alias with matching DNS record shape |
| `did-doc-alias-invalid-domain.json` | Malformed alias (bad domain format) |
| `metadata-doc-full.json` | Complete metadata with all fields, multiple releases |
| `metadata-doc-minimal.json` | Only mandatory fields (id, type, license, authors, security) + one release |
| `metadata-doc-no-releases.json` | Missing `releases` array — triggers `missing_releases` error |
| `metadata-doc-bad-json.json` | Invalid JSON — triggers `invalid_json` error |
| `release-doc-v1.0.0.json` | Full release with version, artifacts (icon, banner, package), provides, requires, suggests, auth |
| `release-doc-no-artifacts.json` | Missing `artifacts` — triggers validation error |
| `release-doc-no-version.json` | Missing `version` — triggers validation error |
| `release-doc-with-requirements.json` | Release with `requires` (env:php, env:wp) and `suggests` (env:wp) |

## Test factories

### MetadataDocumentFactory

```php
class MetadataDocumentFactory {
    /**
     * Create a valid MetadataDocument with all optional fields populated.
     */
    public static function full(): MetadataDocument { ... }

    /**
     * Create a minimal valid MetadataDocument (mandatory fields only).
     */
    public static function minimal(): MetadataDocument { ... }

    /**
     * Create from a fixture JSON file.
     */
    public static function from_fixture( string $name ): MetadataDocument { ... }

    /**
     * Create a builder for targeted field overrides (e.g., missing slug, missing authors).
     */
    public static function builder(): MetadataDocumentBuilder { ... }
}
```

### ReleaseDocumentFactory

```php
class ReleaseDocumentFactory {
    public static function with_version( string $version ): ReleaseDocument { ... }
    public static function from_fixture( string $name ): ReleaseDocument { ... }
    public static function builder(): ReleaseDocumentBuilder { ... }
}
```

## Packages module — DID pipeline unit tests

### Priority A — Pure functions (no WP deps, no mocks needed)

| Test class | Function under test | Key edge cases |
|-----------|-------------------|----------------|
| `GetDidHashTest` | `get_did_hash()` | Error propagation from `parse_did`, deterministic output for same DID, different DIDs produce different hashes, 32-char DID length, Unicode multibyte DIDs |
| `GetLanguagePriorityListTest` | `get_language_priority_list()` | Simple locale (`en`), locale with region (`en-US`), locale with variant (`zh-Hans-CN`), `-x-` private-use subtag skip, underscore-to-hyphen conversion, `de` → `de-DE` doubling, defaults (`en-us`, `en`), filter hook `fair.packages.language_priority_list` fires |
| `PickArtifactByLangTest` | `pick_artifact_by_lang()` | Exact match scores highest, partial match (prefix), no match falls back to first in array, empty artifacts array, single artifact, filter `fair.packages.pick_artifact_by_lang` fires |
| `PickReleaseTest` | `pick_release()` | Sorts descending, null version returns latest, specific version match, version not found returns null, empty releases array |
| `VersionRequirementsTest` | `version_requirements()` | Parses `requires.env:php`, `requires.env:wp`, `suggests.env:wp` → `tested_to`, strips prefix operators (`^1.0` → `1.0`), missing requires/suggests keys, ReleaseDocument with no requirements |
| `GetUnmetRequirementsTest` | `get_unmet_requirements()` | PHP version too low, WP version too low, both met, unknown package type (env:php-ext), invalid comparator, empty requirements |
| `CheckRequirementsTest` | `check_requirements()` | All met returns true, any unmet returns false, empty requires returns true |
| `GetIconsTest` | `get_icons()` | 128×128 → 1x, 256×256 → 2x, SVG detection (content-type contains `svg+xml`), s.w.org SVG → `default` key, no matching icons, empty input |
| `GetBannersTest` | `get_banners()` | 772×250 → low, 1544×500 → high, no matching banners, empty input |
| `GetHashedFilenameTest` | `get_hashed_filename()` | Plugin: slug + `-didhash`/file, Theme: slug + `-didhash` (no subdir), slug already contains didhash (no double-appending), known DID produces expected hash |
| `ValidatePackageAliasTest` | `validate_package_alias()` | Cache hit returns cached value, cache miss calls `fetch_and_validate_package_alias`, sets transient on success |
| `FetchAndValidatePackageAliasTest` | `fetch_and_validate_package_alias()` | Valid `fair://` alias with matching DNS record returns domain, no aliases returns null, multiple aliases returns error, invalid domain format returns error, domain too long (>255 chars) returns error, missing DNS record returns error, DNS record with non-matching DID returns error, record with malformed `did=` format returns error |

### Priority B — Transient/HTTP-dependent (WP test framework, mock filters)

| Test class | Strategy |
|-----------|----------|
| `GetDidDocumentTest` | Pre-seed `site_transient_{cache_key}` for cache hit. For cache miss: mock `FAIR\DID\PLC\PlcClient::resolve_did()` via a test double or filter to return controlled DID document arrays. Verify error caching on `RuntimeException`. Test error cache retrieval (returns cached WP_Error rather than re-fetching). |
| `FetchPackageMetadataTest` | Mock `get_did_document()` return, mock `wp_remote_get()` for metadata HTTP responses. Test cases: successful metadata fetch, no FairPackageManagementRepo service, DID mismatch between fetched metadata and requested DID, HTTP error codes, WP_Error from HTTP layer, cache hit path |
| `FetchMetadataDocTest` | Mock `wp_remote_get()` return. Test cases: valid JSON, invalid JSON (WP_Error), HTTP non-200, section sorting applied, Accept header `application/json+fair` present, local URL timeout reduction, cache hit when localStorage transient exists |
| `GetLatestReleaseFromDidTest` | Mock `get_did_document()` and `fetch_package_metadata()`. Test cases: happy path (keys + metadata + release), no signing keys → WP_Error, no releases → WP_Error, error propagation from upstream |
| `GetPackageDataTest` | Mock `fetch_package_metadata()` and `get_latest_release_from_did()`. Verify response shape: all keys present, short_description truncated at 147 chars + '...', icons/banners arrays populated, `requires_php`/`requires_wp`/`tested_to` fields, theme gets `theme_uri`, `_fair` raw metadata embedded |
| `AddPackageToReleaseCacheTest` | Test: empty DID short-circuits, transient append (existing releases preserved), transient set when none exists |
| `MaybeAddAcceptHeaderTest` | Test: non-GitHub URL returns unchanged, GitHub URL with `application/octet-stream` artifact gets Accept header, GitHub URL with other content-type unchanged |
| `CacheUpdateErrorTest` / `ClearUpdateErrorTest` | Test: error stored with timestamp data, clear removes the transient |
| `SearchByDidTest` | Test: `query_plugins` action only fires on plugin search, non-DID slug returns early, valid ID invokes `get_api_data`, response shape matches expected, non-query_plugins action passes through |
| `GetPluginInformationTest` | Test: `plugin_information` action only, non-DID slug passes through, valid DID returns API data as object, error path returns original result unchanged |

### Priority C — DTO validation (static factories)

| Test class | Test cases |
|-----------|------------|
| `MetadataDocumentTest` | `from_data()`: all valid fields present, missing mandatory field (id, type, license, authors, security — each individually), missing releases array, release with missing version, multiple releases parsed, optional fields absent, keywords/security as arrays. `from_response()`: valid JSON body + headers, invalid JSON body, valid JSON but invalid data |
| `ReleaseDocumentTest` | `from_data()`: all valid fields, missing version, missing artifacts, optional fields (provides, requires, suggests, auth) present, optional fields absent |

### Edge case catalog

For every function in the DID pipeline, these edge case categories must be represented:

1. **Null/empty inputs:** empty string, empty array, null where documented
2. **Invalid types:** integer where string expected, object where array expected
3. **Unicode:** multibyte characters in DIDs, locale strings, package names
4. **Boundary values:** 32-char DID length (exactly), DID hash length (always 6), 255-char domain alias
5. **WP_Error propagation:** when upstream function returns WP_Error, downstream must return it (not crash)
6. **Transient race conditions:** cache expired between check and use (rare but testable with filter injection)
7. **JSON edge cases:** deeply nested, empty objects, null values, duplicate keys, BOM-prefixed, trailing commas
