# Integration Tests

## Philosophy

- Docker-based ephemeral WordPress installs under `tests/sites/ephemeral/<suite-name>/`.
- Each test suite gets its own fully independent WordPress root, MySQL database, and mock server.
- Lifecycle: compose up → seed → run tests → compose down -v.
- CI matrix: PHP 8.0/8.4 × WP 5.4/latest × single-site/multisite.

## Runner script

`bin/run-integration.sh`

```bash
#!/bin/bash
set -euo pipefail

SUITE="${1:-integration}"
COMPOSE_DIR="tests/sites/ephemeral/${SUITE}"
COMPOSE_FILE="${COMPOSE_DIR}/docker-compose.yml"

# 1. Start services
docker compose -f "${COMPOSE_FILE}" up -d --wait

# 2. Install WordPress
docker compose -f "${COMPOSE_FILE}" exec -T wp-cli \
    wp core install \
    --url="${WP_URL:-integration.local}" \
    --title="FAIR Integration Tests" \
    --admin_user=admin \
    --admin_password=password \
    --admin_email=admin@example.org \
    --skip-email

# 3. Activate plugin
docker compose -f "${COMPOSE_FILE}" exec -T wp-cli \
    wp plugin activate fair-plugin

# 4. Seed test data
docker compose -f "${COMPOSE_FILE}" exec -T wp-cli \
    wp eval-file /var/www/html/wp-content/plugins/fair-plugin/tests/sites/ephemeral/${SUITE}/seed.php

# 5. Run PHPUnit
docker compose -f "${COMPOSE_FILE}" exec -T wp-cli \
    php /var/www/html/wp-content/plugins/fair-plugin/vendor/bin/phpunit \
    -c /var/www/html/wp-content/plugins/fair-plugin/tests/integration/phpunit.xml \
    "$@"

# 6. Tear down
docker compose -f "${COMPOSE_FILE}" down -v
```

## Docker Compose design

### Base service definitions (`tests/sites/ephemeral/docker-compose.base.yml`)

```yaml
services:
  mysql:
    image: mariadb:10.6
    environment:
      MYSQL_ROOT_PASSWORD: password
      MYSQL_DATABASE: wordpress_test
    tmpfs:
      - /var/lib/mysql
    healthcheck:
      test: ["CMD", "mariadb-admin", "ping", "--silent"]
      interval: 2s
      retries: 30

  wordpress:
    build:
      context: .
      dockerfile: Dockerfile.wp
      args:
        WORDPRESS_VERSION: "${WORDPRESS_VERSION:-6.4}"
        PHP_VERSION: "${PHP_VERSION:-8.0}"
    depends_on:
      mysql:
        condition: service_healthy
      mock-server:
        condition: service_healthy
    environment:
      WORDPRESS_DB_HOST: mysql
      WORDPRESS_DB_USER: root
      WORDPRESS_DB_PASSWORD: password
      WORDPRESS_DB_NAME: wordpress_test
      WORDPRESS_TABLE_PREFIX: wptests_
    volumes:
      - ../..:/var/www/html/wp-content/plugins/fair-plugin
    healthcheck:
      test: ["CMD", "curl", "-f", "http://localhost/"]
      interval: 3s
      retries: 30

  wp-cli:
    image: wordpress:cli-${PHP_VERSION:-8.0}
    depends_on:
      wordpress:
        condition: service_healthy
    volumes:
      - ../..:/var/www/html/wp-content/plugins/fair-plugin
    user: "33:33"

  mock-server:
    build:
      context: ../../mock-server
    healthcheck:
      test: ["CMD", "curl", "-f", "http://localhost:8080/health"]
      interval: 2s
      retries: 15
```

### Suite-specific compose (`tests/sites/ephemeral/integration/docker-compose.yml`)

```yaml
name: fair-integration-tests
include:
  - ../docker-compose.base.yml
```

### WP Dockerfile (`tests/sites/ephemeral/Dockerfile.wp`)

```dockerfile
ARG WORDPRESS_VERSION=6.4
ARG PHP_VERSION=8.0
FROM wordpress:${WORDPRESS_VERSION}-php${PHP_VERSION}-apache

# Install xdebug for coverage
RUN if command -v pecl >/dev/null 2>&1; then \
        pecl install xdebug && docker-php-ext-enable xdebug; \
    fi

# Configure xdebug
RUN echo "xdebug.mode=coverage" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini \
    && echo "xdebug.start_with_request=yes" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini
```

## Mock DID resolution server

A lightweight PHP server that emulates the PLC directory and FAIR repository APIs. Located at `tests/mock-server/`.

```
tests/mock-server/
├── Dockerfile
├── index.php            # Router
├── health               # Health check endpoint
├── fixtures/
│   ├── plc-directory/   # DID document JSON blobs keyed by did:plc:...
│   └── fair-repo/       # Metadata document JSON blobs keyed by DID
└── log/                 # Request log for assertions in tests
```

**Endpoints:**

| Route | Response | Notes |
|-------|----------|-------|
| `GET /did/{did}` | DID document JSON | Mirrors PLC directory `/did:plc:xxx` endpoint |
| `GET /repo/{did}/metadata` | Metadata document JSON | Mirrors FAIR repo endpoint with `application/json+fair` Accept |
| `GET /health` | `200 OK` | Docker healthcheck |
| `GET /log` | JSON array of logged requests | Used by integration tests to assert HTTP interactions |

**Configuration:** The WordPress test site sets `FAIR_DEFAULT_REPO_DOMAIN` to point at `mock-server` and the PHP bootstrap adds a filter that redirects PLC client HTTP calls to the mock server. Or the mock server runs on a known hostname (`mock-server`) within the Docker network and DNS resolution handles routing.

## Seeding

`tests/sites/ephemeral/<suite>/seed.php` — a WP-CLI eval script that:

1. Creates a test plugin directory under `wp-content/plugins/test-package-XXXXXX/` with a valid `Plugin ID: did:plc:test...` header and `Version: 1.0.0`
2. Pre-populates transients with fixture DID documents and metadata
3. Registers the test plugin with `FAIR\Updater\Updater::register_plugin()`
4. Populates pre-configured test users (admin for HTTP tests)

## Integration test cases

### Install flow

| Test | Description |
|------|-------------|
| `InstallFlowTest::test_install_plugin_by_did` | Full install pipeline: parse DID → resolve DID doc → fetch metadata → select release → download artifact → verify signature → move to correct directory with DID hash suffix |
| `InstallFlowTest::test_install_plugin_unmet_requirements` | PHP version too low → install blocked with appropriate error |
| `InstallFlowTest::test_install_plugin_no_signing_keys` | DID doc has no valid Multikey → install blocked |
| `InstallFlowTest::test_install_plugin_directory_naming` | Verifies installed directory ends with `-<didhash>` |

### Update transient

| Test | Description |
|------|-------------|
| `UpdateTransientTest::test_update_available` | Newer remote version → appears in `$transient->response` |
| `UpdateTransientTest::test_no_update_current` | Same version → appears in `$transient->no_update` with View Details link |
| `UpdateTransientTest::test_no_package_below_minimum` | Version below minimum → no response entry |
| `UpdateTransientTest::test_multisite_update_transient` | Same behavior on multisite (ms-required group) |

### Signature verification

| Test | Description |
|------|-------------|
| `SignatureVerificationIntegrationTest::test_valid_signature` | Artifact with valid Ed25519 signature from trusted key → passes |
| `SignatureVerificationIntegrationTest::test_invalid_signature` | Tampered artifact → WP_Error |
| `SignatureVerificationIntegrationTest::test_missing_signature` | Artifact without signature field → WP_Error |
| `SignatureVerificationIntegrationTest::test_untrusted_key` | Signature from key not in DID doc → WP_Error |

### WP-CLI compatibility

| Test | Description |
|------|-------------|
| `WpCliCompatTest::test_did_to_path` | `wp plugin --did=<did>` maps to correct filesystem path |
| `WpCliCompatTest::test_path_to_did` | Filesystem path maps back to DID |

## Group annotations

| Group | Meaning | CI behavior |
|-------|---------|-------------|
| `slow` | Tests that download artifacts or install real packages | Skipped in PR CI, run on main/RC |
| `ms-required` | Multisite-only tests | Only run in multisite matrix |
| `ms-excluded` | Single-site-only tests | Only run in single-site matrix |
