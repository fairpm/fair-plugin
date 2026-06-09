#!/usr/bin/env bash
# ────────────────────────────────────────────────────────────────────
# FAIR Plugin — Integration Test Runner
#
# Spins up ephemeral Docker services, runs integration tests against
# a clean WordPress install, then tears EVERYTHING down.
#
# Usage:
#   bin/run-integration.sh [suite-name] [phpunit-args...]
#
# Environment variables:
#   WP_VERSION    — WordPress version (default: 6.4)
#   PHP_VERSION   — PHP version for test container (default: 8.0)
#   WP_MULTISITE  — set to "1" for multisite (default: unset)
#
# Exit codes:
#   0 – all tests passed
#   N – PHPUnit or setup error
# ────────────────────────────────────────────────────────────────────
set -euo pipefail

SUITE="${1:-integration}"
shift || true
PHPUNIT_ARGS="${*:-}"

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"
COMPOSE_DIR="${PROJECT_DIR}/tests/sites/ephemeral/${SUITE}"
COMPOSE_FILE="${COMPOSE_DIR}/docker-compose.yml"
COMPOSE_PROJECT="fair-integration-${SUITE}"

WP_VERSION="${WP_VERSION:-6.4}"
PHP_VERSION="${PHP_VERSION:-8.0}"
MULTISITE="${WP_MULTISITE:-0}"

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

say()  { echo -e "${GREEN}→${NC} $*"; }
warn() { echo -e "${YELLOW}⚠${NC} $*" >&2; }
die()  { echo -e "${RED}✗${NC} $*" >&2; exit 1; }

# ── Pre-flight ─────────────────────────────────────────────────────
command -v docker >/dev/null 2>&1 || die "Docker is required. Install it from https://docker.com"
docker compose version >/dev/null 2>&1 || die "Docker Compose v2 is required."

if [ ! -f "$COMPOSE_FILE" ]; then
	die "Compose file not found: $COMPOSE_FILE\n  Run: mkdir -p $COMPOSE_DIR && create docker-compose.yml"
fi

say "Suite:       ${SUITE}"
say "WP version:  ${WP_VERSION}"
say "PHP version: ${PHP_VERSION}"
say "Multisite:   ${MULTISITE}"

# ── Teardown guarantee ──────────────────────────────────────────────
# No matter how the script exits, we clean up.  trap EXIT is the
# single source of truth for teardown — no early returns without it.
cleanup() {
	local exit_code=$?
	say "Tearing down..."
	docker compose \
		--project-name "${COMPOSE_PROJECT}" \
		-f "${COMPOSE_FILE}" \
		down --volumes --remove-orphans --timeout 10 \
		2>/dev/null || true

	# Belt and suspenders: kill any leftover containers from this project.
	local leftovers
	leftovers=$(docker ps -q --filter "label=com.docker.compose.project=${COMPOSE_PROJECT}" 2>/dev/null)
	if [ -n "$leftovers" ]; then
		warn "Force-removing leftover containers..."
		echo "$leftovers" | xargs docker rm -f 2>/dev/null || true
	fi

	# Remove the project network if it wasn't cleaned up.
	docker network rm "${COMPOSE_PROJECT}_default" 2>/dev/null || true

	exit $exit_code
}
trap cleanup EXIT INT TERM

# ── Start services ──────────────────────────────────────────────────
say "Starting services..."
export WP_VERSION PHP_VERSION

docker compose \
	--project-name "${COMPOSE_PROJECT}" \
	-f "${COMPOSE_FILE}" \
	build --quiet 2>&1 | sed 's/^/  /' || die "Build failed"

docker compose \
	--project-name "${COMPOSE_PROJECT}" \
	-f "${COMPOSE_FILE}" \
	up --detach --wait --wait-timeout 60 \
	2>&1 | sed 's/^/  /' || die "Services failed to start"

say "All services healthy."

# ── Install WordPress ───────────────────────────────────────────────
say "Installing WordPress..."
WP_CLI="docker compose --project-name ${COMPOSE_PROJECT} -f ${COMPOSE_FILE} exec -T wp-cli"

if [ "$MULTISITE" = "1" ]; then
	$WP_CLI wp core multisite-install \
		--url="integration.local" \
		--title="FAIR Integration Tests" \
		--admin_user=admin \
		--admin_password=password \
		--admin_email=admin@example.org \
		--skip-email 2>&1 | sed 's/^/  /' || die "WP multisite install failed"
else
	$WP_CLI wp core install \
		--url="integration.local" \
		--title="FAIR Integration Tests" \
		--admin_user=admin \
		--admin_password=password \
		--admin_email=admin@example.org \
		--skip-email 2>&1 | sed 's/^/  /' || die "WP install failed"
fi

# ── Activate plugin ─────────────────────────────────────────────────
say "Activating plugin..."
$WP_CLI wp plugin activate fair-plugin --network 2>&1 | sed 's/^/  /' || die "Plugin activation failed"

# ── Seed test data ──────────────────────────────────────────────────
SEED_FILE="/var/www/html/wp-content/plugins/fair-plugin/tests/sites/ephemeral/${SUITE}/seed.php"
if $WP_CLI test -f "$SEED_FILE" 2>/dev/null; then
	say "Seeding test data..."
	$WP_CLI wp eval-file "$SEED_FILE" 2>&1 | sed 's/^/  /' || warn "Seed script had errors (non-fatal)"
else
	say "No seed file at ${SEED_FILE} — skipping."
fi

# ── Quick smoke test ─────────────────────────────────────────────────
say "Smoke test: mock server health..."
$WP_CLI curl -s http://mock-server:8080/health 2>&1 | sed 's/^/  /'

say "Smoke test: DID document lookup..."
$WP_CLI curl -s http://mock-server:8080/did:plc:z72i7hdynmk6r22z27h6tvur 2>&1 | head -3 | sed 's/^/  /'

say "Smoke test: Metadata lookup..."
$WP_CLI curl -s http://mock-server:8080/metadata/did:plc:z72i7hdynmk6r22z27h6tvur 2>&1 | head -3 | sed 's/^/  /'

# ── Run tests ───────────────────────────────────────────────────────
say "Running integration tests..."
PHPUNIT_XML="/var/www/html/wp-content/plugins/fair-plugin/tests/integration/phpunit.xml"

set +e
$WP_CLI php /var/www/html/wp-content/plugins/fair-plugin/vendor/bin/phpunit \
	-c "$PHPUNIT_XML" \
	$PHPUNIT_ARGS \
	2>&1
INTEG_EXIT=$?
set -e

say "Running HTTP tests..."
HTTP_PHPUNIT_XML="/var/www/html/wp-content/plugins/fair-plugin/tests/http/phpunit.xml"
if $WP_CLI test -f "$HTTP_PHPUNIT_XML" 2>/dev/null; then
	set +e
	$WP_CLI php /var/www/html/wp-content/plugins/fair-plugin/vendor/bin/phpunit \
		-c "$HTTP_PHPUNIT_XML" \
		$PHPUNIT_ARGS \
		2>&1
	HTTP_EXIT=$?
	set -e
else
	HTTP_EXIT=0
	say "No HTTP test config found — skipping."
fi

TEST_EXIT=$(( INTEG_EXIT > HTTP_EXIT ? INTEG_EXIT : HTTP_EXIT ))

if [ $TEST_EXIT -eq 0 ]; then
	say "${GREEN}All integration + HTTP tests passed.${NC}"
else
	if [ $INTEG_EXIT -ne 0 ]; then
		warn "Integration tests failed with exit code ${INTEG_EXIT}"
	fi
	if [ $HTTP_EXIT -ne 0 ]; then
		warn "HTTP tests failed with exit code ${HTTP_EXIT}"
	fi
fi

# Script exits here → trap EXIT fires → cleanup runs.
exit $TEST_EXIT
