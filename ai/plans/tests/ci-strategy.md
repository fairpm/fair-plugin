# CI Strategy

## Overview

GitHub Actions workflow with layered gates: fastest tests first, slow/destructive tests gated to main/RC branches. Matrix covers the full supported range: PHP 8.0–8.4 × WP 5.4–latest × single-site/multisite.

## Workflow: `test.yml`

```yaml
name: Test Suite
on:
  push:
    branches: [main, development, 'release/**']
  pull_request:
    branches: [main, development]

jobs:
  # ── Gate 1: Lint (always, fastest) ──
  lint:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: php-actions/composer@v6
        with:
          php_version: '8.0'
      - run: composer run lint:phpcs
      - run: composer run lint:phpstan

  # ── Gate 2: Unit tests (always, no Docker, matrix) ──
  unit:
    needs: lint
    runs-on: ubuntu-latest
    strategy:
      fail-fast: false
      matrix:
        php: ['8.0', '8.4']
        wp: ['5.4', 'latest']
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with:
          php-version: ${{ matrix.php }}
          coverage: xdebug
      - name: Start MySQL
        run: sudo systemctl start mysql
      - name: Create test database
        run: mysql -uroot -proot -e "CREATE DATABASE wordpress_test"
      - run: composer install
      - name: Install WP test suite
        run: bash bin/install-wp-tests.sh wordpress_test root root localhost ${{ matrix.wp }}
      - name: Run unit tests
        run: composer run test:unit

  # ── Gate 3: Integration tests (always, Docker, matrix) ──
  integration:
    needs: lint
    runs-on: ubuntu-latest
    strategy:
      fail-fast: false
      matrix:
        php: ['8.0', '8.4']
        wp: ['5.4', 'latest']
        site: ['single', 'multisite']
    steps:
      - uses: actions/checkout@v4
      - name: Run integration tests
        run: bin/run-integration.sh integration
        env:
          PHP_VERSION: ${{ matrix.php }}
          WORDPRESS_VERSION: ${{ matrix.wp }}
          WP_MULTISITE: ${{ matrix.site == 'multisite' && '1' || '0' }}

  # ── Gate 4: HTTP tests (always, against integration ephemeral site) ──
  http:
    needs: integration
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - name: Run HTTP tests
        run: |
          bin/run-integration.sh http &
          sleep 30
          composer run test:http

  # ── Gate 5: Browser tests - fast (always) ──
  browser-fast:
    needs: integration
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: actions/setup-node@v4
        with:
          node-version: '20'
      - name: Install Playwright
        run: |
          cd tests/browser
          npm ci
          npx playwright install chromium --with-deps
      - name: Start ephemeral site
        run: bin/run-integration.sh browser-test &
      - name: Run browser tests (excluding slow)
        run: npx playwright test --grep-invert @slow
        working-directory: tests/browser

  # ── Gate 6: Browser tests - slow (main/RC only) ──
  browser-slow:
    if: github.ref == 'refs/heads/main' || github.ref == 'refs/heads/development' || startsWith(github.ref, 'refs/heads/release/')
    needs: browser-fast
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: actions/setup-node@v4
        with:
          node-version: '20'
      - name: Install Playwright
        run: |
          cd tests/browser
          npm ci
          npx playwright install chromium --with-deps
      - name: Start ephemeral site
        run: bin/run-integration.sh browser-test &
      - name: Run slow browser tests
        run: npx playwright test --grep @slow
        working-directory: tests/browser
```

## Gate summary

| Gate | Runs on | Approx. time | Matrix |
|------|---------|-------------|--------|
| Lint (PHPCS + PHPStan) | All pushes/PRs | ~1 min | PHP 8.0 only |
| Unit tests | All pushes/PRs | ~2 min | PHP 8.0/8.4 × WP 5.4/latest |
| Integration tests | All pushes/PRs | ~8 min | PHP 8.0/8.4 × WP 5.4/latest × single/multisite |
| HTTP tests | All pushes/PRs | ~3 min | Against latest PHP/WP |
| Browser (fast) | All pushes/PRs | ~5 min | Chromium only |
| Browser (slow) | main/dev/RC | ~10 min | Chromium only |

## Composer script updates

```json
{
  "scripts": {
    "test:unit": "php ./vendor/phpunit/phpunit/phpunit -c tests/unit/phpunit.xml",
    "test:unit:multisite": "php ./vendor/phpunit/phpunit/phpunit -c tests/unit/multisite.xml",
    "test:integration": "php ./vendor/phpunit/phpunit/phpunit -c tests/integration/phpunit.xml",
    "test:http": "php ./vendor/phpunit/phpunit/phpunit -c tests/http/phpunit.xml",
    "test:all": [
      "@test:unit",
      "@test:integration",
      "@test:http"
    ]
  }
}
```

## Coverage reporting

- Unit test coverage: `tests/unit/phpunit.xml` includes `<coverage>` targeting `./inc`.
- Integration test coverage: separate coverage report, merged with unit via `phpunit-merger` (existing tooling).
- Combined coverage report at `tests/coverage/html/full`.
- Coverage threshold: not enforced initially — set after baseline is established and Infection passes.
- Infection: added in follow-up pass after coverage reaches target levels.

## Known limitations

- Integration tests in CI don't test the actual PLC directory or AspireCloud — they rely on the mock server. A separate periodic "smoke test" workflow (daily cron) will target the real AspireCloud when it's ready.
- Multisite unit tests require the WP test suite to be installed with multisite config. The `bin/install-wp-tests.sh` script accepts a version argument; add a `--multisite` flag for the multisite matrix.
- HTTP tests in CI depend on the integration stack being up. The workflow uses a dependency chain (`needs: integration`), but the ephemeral site from the integration job doesn't survive across jobs in GitHub Actions. Solution: HTTP tests run their own Docker compose as part of their job (`bin/run-integration.sh http`), not against the integration job's containers.
