# Test Directory Layout

## Top-level structure

```
tests/
├── unit/                              # Unit tests (WP test framework, no Docker)
│   ├── bootstrap.php                  # ← copied from tests/phpunit/bootstrap.php
│   ├── phpunit.xml                    # ← migrated from phpunit.xml.dist
│   ├── multisite.xml                  # ← migrated from tests/phpunit/multisite.xml
│   └── tests/
│       ├── Packages/
│       │   ├── ParseDidTest.php               # (existing, migrated)
│       │   ├── GetDidServiceTest.php          # (existing, migrated)
│       │   ├── GetFairSigningKeysTest.php     # (existing, migrated)
│       │   ├── GetDidHashTest.php
│       │   ├── GetLanguagePriorityListTest.php
│       │   ├── PickArtifactByLangTest.php
│       │   ├── PickReleaseTest.php
│       │   ├── VersionRequirementsTest.php
│       │   ├── GetUnmetRequirementsTest.php
│       │   ├── CheckRequirementsTest.php
│       │   ├── GetIconsTest.php
│       │   ├── GetBannersTest.php
│       │   ├── GetHashedFilenameTest.php
│       │   ├── GetInstalledVersionTest.php
│       │   ├── MetadataDocumentTest.php
│       │   ├── ReleaseDocumentTest.php
│       │   ├── CacheUpdateErrorTest.php
│       │   ├── ClearUpdateErrorTest.php
│       │   ├── MaybeAddAcceptHeaderTest.php
│       │   ├── ValidatePackageAliasTest.php
│       │   ├── FetchAndValidatePackageAliasTest.php
│       │   ├── SearchByDidTest.php
│       │   ├── GetPluginInformationTest.php
│       │   └── ...
│       ├── Updater/
│       │   ├── UpdaterTest.php
│       │   ├── PluginPackageTest.php
│       │   ├── ThemePackageTest.php
│       │   ├── GetPackagesTest.php
│       │   ├── SignatureVerificationTest.php
│       │   ├── GetTrustedKeysTest.php
│       │   ├── RegisterPluginRowHooksTest.php
│       │   └── DisplayPluginUpdateErrorTest.php
│       ├── Compatibility/
│       │   └── PolyfillTest.php
│       ├── Avatars/
│       │   ├── FilterAvatarTest.php
│       │   ├── FilterAvatarUrlTest.php
│       │   ├── GetAvatarUrlTest.php
│       │   ├── GenerateDefaultAvatarTest.php
│       │   ├── ShouldReplaceUrlTest.php
│       │   └── SaveAvatarUploadTest.php
│       ├── Salts/
│       │   ├── ReplaceSaltGenerationViaApiTest.php
│       │   ├── GenerateSaltStringTest.php
│       │   └── GenerateSaltResponseBodyTest.php
│       ├── Pings/
│       │   ├── RemovePingomaticTest.php
│       │   ├── GetIndexnowKeyTest.php
│       │   ├── PingIndexnowTest.php
│       │   └── HandleKeyFileRequestTest.php
│       ├── DefaultRepo/
│       │   ├── GetDefaultRepoDomainTest.php
│       │   ├── ReplaceRepoApiUrlsTest.php
│       │   └── RemoveFavoritesTabTest.php
│       ├── VersionCheck/
│       │   ├── ReplaceBrowserVersionCheckTest.php
│       │   ├── GetBrowserCheckResponseTest.php
│       │   ├── ParseUserAgentTest.php
│       │   ├── CheckPhpVersionTest.php
│       │   └── GetPhpBranchesTest.php
│       ├── Settings/
│       │   └── LoadSingleSiteAvatarSettingsTest.php
│       ├── Upgrades/
│       │   └── RunPluginUpgradeProcessesTest.php
│       └── SampleTest.php                    # (existing, migrated)
│
├── fixtures/                          # Shared JSON fixture files
│   ├── did-doc-valid.json
│   ├── did-doc-no-keys.json
│   ├── did-doc-no-services.json
│   ├── did-doc-alias-valid.json
│   ├── did-doc-alias-invalid-domain.json
│   ├── metadata-doc-full.json
│   ├── metadata-doc-minimal.json
│   ├── metadata-doc-no-releases.json
│   ├── metadata-doc-bad-json.json
│   ├── release-doc-v1.0.0.json
│   ├── release-doc-no-artifacts.json
│   ├── release-doc-no-version.json
│   └── release-doc-with-requirements.json
│
├── factory/                           # Test factories (builder pattern)
│   ├── class-metadata-document-factory.php
│   └── class-release-document-factory.php
│
├── integration/                       # Docker-based integration tests
│   ├── bootstrap.php
│   ├── phpunit.xml
│   └── tests/
│       ├── Packages/
│       │   ├── InstallFlowTest.php
│       │   ├── UpdateTransientTest.php
│       │   └── MovePackageDuringInstallTest.php
│       ├── Updater/
│       │   ├── FullUpdatePipelineTest.php
│       │   └── SignatureVerificationIntegrationTest.php
│       └── ...
│
├── http/                              # HTTP-level tests
│   ├── bootstrap.php
│   ├── phpunit.xml
│   └── tests/
│       ├── AdminAjaxTest.php
│       ├── PluginsApiTest.php
│       ├── UpdateTransientShapeTest.php
│       ├── IndexNowKeyTest.php
│       ├── SaltApiTest.php
│       └── ...
│
├── browser/                           # Playwright browser tests
│   ├── package.json
│   ├── playwright.config.ts
│   ├── .auth/
│   │   └── admin.json                 # pre-authenticated state
│   └── specs/
│       ├── direct-install.spec.ts
│       ├── search-did.spec.ts
│       ├── install-activate-update.spec.ts
│       ├── avatar-upload.spec.ts
│       └── update-error-row.spec.ts
│
└── sites/
    ├── ephemeral/                      # Docker-based throwaway sites (CI + local)
    │   ├── docker-compose.base.yml     # shared service definitions
    │   ├── Dockerfile.wp               # parameterized WP image
    │   ├── integration/
    │   │   ├── docker-compose.yml
    │   │   ├── wp-tests-config.php
    │   │   └── seed.php
    │   ├── http/
    │   │   ├── docker-compose.yml
    │   │   ├── wp-tests-config.php
    │   │   └── seed.php
    │   └── browser-test/
    │       ├── docker-compose.yml
    │       ├── wp-tests-config.php
    │       └── seed.php
    └── static/                         # Persistent "pet" scenario configs
        ├── bedrock/                    # Roots Bedrock (Composer-managed WP)
        │   └── README.md               # setup instructions, known gotchas
        └── exotic-sap/                 # example: SAP-hosted, custom dir layout
            └── README.md               # reproduction steps, FAIR breakage notes
```

## Migration from current layout

The existing `tests/phpunit/` tree maps to `tests/unit/`:

| Old path | New path |
|----------|----------|
| `tests/phpunit/bootstrap.php` | `tests/unit/bootstrap.php` |
| `tests/phpunit/multisite.xml` | `tests/unit/multisite.xml` |
| `tests/phpunit/tests/` | `tests/unit/tests/` |
| `tests/phpunit/tests/Packages/*` | `tests/unit/tests/Packages/*` |
| `tests/phpunit/tests/SampleTest.php` | `tests/unit/tests/SampleTest.php` |

`phpunit.xml.dist` at the project root becomes `tests/unit/phpunit.xml` and paths are adjusted to be relative to the `tests/unit/` directory. The composer script `test` is updated to point at the new config location.

## Naming conventions

Following the conventions in `SampleTest.php`:

- **File name:** `tests/unit/tests/<Module>/<FunctionName>Test.php`
- **Class name:** `<FunctionName>Test`
- **Method name:** `test_should(_not)_do_<something>[_when_<condition>]()`
- **Coverage:** `@covers` at class level targeting the function/class under test
- **Data providers:** Prefixed with `data_`, datasets are named
