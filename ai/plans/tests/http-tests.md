# HTTP Tests

## Philosophy

- Real HTTP requests against a running WordPress instance (the full stack).
- Don't need a browser — test JSON responses, AJAX endpoints, and admin page rendering.
- Base URL is configurable via environment variable: ephemeral Docker site or a "pet" staging instance.
- When pointed at a pet instance, destructive tests are skipped (`@group destructive` annotation).
- Uses the WordPress PHPUnit framework with `wp_remote_get()` / `wp_remote_post()`.

## Configuration

```php
// tests/http/phpunit.xml
<php>
    <env name="FAIR_TEST_BASE_URL" value="http://integration.local"/>
    <env name="FAIR_TEST_ADMIN_USER" value="admin"/>
    <env name="FAIR_TEST_ADMIN_PASS" value="password"/>
</php>
```

Overridable at runtime:

```bash
# Target ephemeral site from integration suite
FAIR_TEST_BASE_URL=http://localhost:8080 composer run test:http

# Target pet staging instance (destructive tests skipped)
FAIR_TEST_BASE_URL=https://staging.example.com \
  FAIR_TEST_ADMIN_USER=myadmin \
  FAIR_TEST_ADMIN_PASS=secret \
  composer run test:http -- --exclude-group destructive
```

## Bootstrap

```php
// tests/http/bootstrap.php
// 1. Read config from env
// 2. Authenticate via wp_remote_post to /wp-login.php
// 3. Store auth cookie for subsequent requests
// 4. Provide helper: $this->get('/wp-admin/admin-ajax.php?action=...')
// 5. Provide helper: $this->post('/wp-admin/admin-ajax.php', [...])
// 6. Assertions wrap responses from wp_remote_* helpers
```

## HTTP test cases

### Admin AJAX

| Test | Description |
|------|-------------|
| `AdminAjaxTest::test_plugin_info_for_did` | `admin-ajax.php?action=plugin-information&slug=did:plc:...` returns valid plugin info JSON |
| `AdminAjaxTest::test_plugin_info_non_did_slug` | Non-DID slug → default WP.org response (pass-through) |
| `AdminAjaxTest::test_check_plugin_dependencies_slug_rewrite` | DID slug containing `-did--` is rewritten to hashed slug |
| `AdminAjaxTest::test_direct_install_form_submission` | POST to `plugin-install.php?tab=fair_direct` with `plugin_id=did:plc:...` → redirect to plugin info |

### Plugins API

| Test | Description |
|------|-------------|
| `PluginsApiTest::test_search_by_did` | `plugins_api('query_plugins', ['search' => 'did:plc:...'])` returns 1 result with correct shape |
| `PluginsApiTest::test_search_non_did_passes_through` | Non-DID search → original results untouched |
| `PluginsApiTest::test_plugin_information_for_did` | `plugins_api('plugin_information', ['slug' => 'did:plc:...'])` returns full plugin info |
| `PluginsApiTest::test_theme_information_for_did` | `themes_api('theme_information', ['slug' => 'did:plc:...'])` returns full theme info |
| `PluginsApiTest::test_fair_plugin_search_result_slug` | Search results for FAIR plugins have `-did--method--msid` suffix in slug |

### Update transient shape validation

| Test | Description |
|------|-------------|
| `UpdateTransientShapeTest::test_response_shape_for_update` | `$transient->response[plugin]` has all expected keys (slug, new_version, package, url, sections, icons, banners, requires, requires_php, tested) |
| `UpdateTransientShapeTest::test_response_shape_for_no_update` | `$transient->no_update[plugin]` has all expected keys with same version |

### IndexNow

| Test | Description |
|------|-------------|
| `IndexNowKeyTest::test_key_file_served` | `GET /fair-indexnow-{key}` returns 200 with the key in plain text |
| `IndexNowKeyTest::test_key_file_invalid` | `GET /fair-indexnow-{wrong-key}` returns 403 |
| `IndexNowKeyTest::test_key_file_caching_headers` | Response has `Cache-Control: public, max-age=31536000` and `Expires` header |

### Salts

| Test | Description |
|------|-------------|
| `SaltApiTest::test_salt_generation_response` | `GET https://api.wordpress.org/secret-key/1.1/salt` (intercepted) returns 200 with valid salt defines |
| `SaltApiTest::test_salt_unique_per_request` | Two requests return different salts |
| `SaltApiTest::test_salt_all_eight_keys_present` | Response contains all 8 key names (AUTH_KEY through NONCE_SALT) |

### Version check

| Test | Description |
|------|-------------|
| `VersionCheckTest::test_browse_happy_response` | `POST api.wordpress.org/core/browse-happy/1.0` with user agent returns valid JSON |
| `VersionCheckTest::test_serve_happy_response` | `GET api.wordpress.org/core/serve-happy/1.0?php_version=8.0` returns valid PHP version check |
| `VersionCheckTest::test_browser_check_response_shape` | Response has platform, name, version, upgrade, insecure, update_url fields |

### Avatars

| Test | Description |
|------|-------------|
| `AvatarTest::test_gravatar_url_replaced` | Comment with gravatar.com avatar → URL is replaced with local or generated avatar |
| `AvatarTest::test_default_avatar_svg` | User without custom avatar → data:image/svg+xml URI returned |
| `AvatarTest::test_custom_avatar` | User with uploaded avatar → attachment URL returned |

### Default repo

| Test | Description |
|------|-------------|
| `DefaultRepoTest::test_api_redirect` | Request to `api.wordpress.org/plugins/info/1.2` is redirected to `api.aspirecloud.net/...` |
| `DefaultRepoTest::test_fair_version_query_arg` | Redirected URL includes `_fair=<version>` query arg |
| `DefaultRepoTest::test_favorites_tab_removed` | `install_plugins_tabs` filter output does not include Favorites tab |

## Group annotations

| Group | Meaning | CI behavior |
|-------|---------|-------------|
| (none) | Fast, read-only | Always runs |
| `destructive` | Installs/uninstalls plugins, modifies WP state | Skipped against pet instances, runs in CI against ephemeral |

## Directory structure for Docker-backed runs

When running HTTP tests against ephemeral infrastructure (CI), use the same Docker Compose pattern as integration tests:

```
tests/sites/ephemeral/http/
├── docker-compose.yml     # includes ../docker-compose.base.yml
├── wp-tests-config.php    # DB creds matching compose
└── seed.php               # creates test users, pre-populates data
```

The HTTP test bootstrap connects to `http://localhost:8080` (the Docker host port), and the runner script runs `compose up` before tests and `compose down -v` after.
