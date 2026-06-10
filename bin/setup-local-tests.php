<?php
/**
 * Test environment setup — downloads WordPress core, test suite, and configures
 * MySQL for local `composer run test:unit` execution.
 *
 * Auto-detects MySQL (local socket → TCP → Docker container).
 * Downloads test dependencies via HTTP (no svn required).
 * Safe to run repeatedly — skips already-downloaded assets.
 *
 * Usage: php bin/setup-local-tests.php
 * Exit:  0 = ready, 1 = failed, 2 = cannot continue
 *
 * @package FAIR
 */

declare( strict_types = 1 );

// ── Configurable defaults ──────────────────────────────────────────
$DB_NAME = getenv( 'FAIR_TEST_DB_NAME' ) ?: 'fair_test';
$DB_USER = getenv( 'FAIR_TEST_DB_USER' ) ?: 'root';
$DB_PASS = getenv( 'FAIR_TEST_DB_PASS' ) ?: '';
$DB_HOST = getenv( 'FAIR_TEST_DB_HOST' ) ?: '127.0.0.1';
$DB_PORT = getenv( 'FAIR_TEST_DB_PORT' ) ?: null;

$TMPDIR       = rtrim( sys_get_temp_dir(), '/\\' );
$WP_CORE_DIR  = getenv( 'WP_CORE_DIR' )  ?: "{$TMPDIR}/wordpress";
$WP_TESTS_DIR = getenv( 'WP_TESTS_DIR' ) ?: "{$TMPDIR}/wordpress-tests-lib";

$DOCKER_MYSQL_PORT = getenv( 'FAIR_TEST_DOCKER_MYSQL_PORT' ) ?: '3309';

// Which WordPress tag/version to download test suite from.
$WP_VERSION = getenv( 'FAIR_TEST_WP_VERSION' ) ?: '6.2.0';

// ── Helpers ────────────────────────────────────────────────────────

function say( string $msg, string $prefix = '→' ): void {
	fwrite( STDERR, "{$prefix} {$msg}\n" );
}

function bail( string $msg, int $code = 2 ): never {
	say( $msg, '✗' );
	exit( $code );
}

function has_cmd( string $cmd ): bool {
	$which = trim( (string) shell_exec( "command -v {$cmd} 2>/dev/null" ) );
	return $which !== '' && $which !== '0';
}

function download( string $url, string $dest, string $description = '' ): bool {
	if ( file_exists( $dest ) ) {
		say( "Already downloaded: {$description}" );
		return true;
	}

	say( "Downloading {$description}..." );

	$dir = dirname( $dest );
	if ( ! is_dir( $dir ) ) {
		mkdir( $dir, 0755, true );
	}

	// Try curl first, then wget, then PHP streams.
	if ( has_cmd( 'curl' ) ) {
		$esc_url = escapeshellarg( $url );
		$esc_dst = escapeshellarg( $dest );
		$output  = shell_exec( "curl -sSL -o {$esc_dst} {$esc_url} 2>&1" );
		return file_exists( $dest ) && filesize( $dest ) > 0;
	}

	if ( has_cmd( 'wget' ) ) {
		$esc_url = escapeshellarg( $url );
		$esc_dst = escapeshellarg( $dest );
		shell_exec( "wget -q -O {$esc_dst} {$esc_url} 2>&1" );
		return file_exists( $dest ) && filesize( $dest ) > 0;
	}

	// PHP streams fallback.
	$content = @file_get_contents( $url );
	if ( $content === false || $content === '' ) {
		return false;
	}
	file_put_contents( $dest, $content );
	return filesize( $dest ) > 0;
}

/**
 * Recursively copy a directory.
 */
function recurse_copy( string $src, string $dst ): void {
	$dir = opendir( $src );
	if ( ! $dir ) {
		return;
	}
	if ( ! is_dir( $dst ) ) {
		mkdir( $dst, 0755, true );
	}
	while ( ( $file = readdir( $dir ) ) !== false ) {
		if ( $file === '.' || $file === '..' ) {
			continue;
		}
		$src_path = "{$src}/{$file}";
		$dst_path = "{$dst}/{$file}";
		if ( is_dir( $src_path ) ) {
			recurse_copy( $src_path, $dst_path );
		} else {
			copy( $src_path, $dst_path );
		}
	}
	closedir( $dir );
}

/**
 * Extract a .tar.gz to a directory.
 */
function extract_tar_gz( string $archive, string $dest_dir ): bool {
	if ( has_cmd( 'tar' ) ) {
		$esc_arc = escapeshellarg( $archive );
		$esc_dst = escapeshellarg( $dest_dir );
		shell_exec( "tar -xzf {$esc_arc} -C {$esc_dst} --strip-components=1 2>&1" );
		return is_dir( $dest_dir ) && count( scandir( $dest_dir ) ) > 2;
	}

	// PHP fallback using PharData.
	if ( class_exists( 'PharData' ) ) {
		try {
			$phar = new PharData( $archive );
			$phar->extractTo( $dest_dir, null, true );
			return true;
		} catch ( \Throwable $e ) {
			say( "PharData extraction failed: {$e->getMessage()}", ' ' );
		}
	}

	return false;
}

/**
 * Extract a .zip to a directory.
 */
function extract_zip( string $archive, string $dest_dir ): bool {
	if ( has_cmd( 'unzip' ) ) {
		$esc_arc = escapeshellarg( $archive );
		$esc_dst = escapeshellarg( $dest_dir );
		shell_exec( "unzip -q -o {$esc_arc} -d {$esc_dst} 2>&1" );
		return is_dir( $dest_dir ) && count( scandir( $dest_dir ) ) > 2;
	}

	if ( class_exists( 'ZipArchive' ) ) {
		$zip = new ZipArchive();
		if ( $zip->open( $archive ) === true ) {
			$zip->extractTo( $dest_dir );
			$zip->close();
			return true;
		}
	}

	return false;
}

/**
 * Try to connect to MySQL — returns true if reachable.
 */
function mysql_is_reachable( string $host, string $user, string $pass, ?string $port = null ): bool {
	try {
		$mysqli = @new mysqli( $host, $user, $pass, '', (int) ( $port ?: '3306' ) );
		if ( $mysqli->connect_errno === 0 ) {
			$mysqli->close();
			return true;
		}
	} catch ( \Throwable $e ) {
		// Connection failed.
	}
	return false;
}

/**
 * Find a local MySQL instance by probing common sockets and TCP ports.
 */
function find_local_mysql(): ?array {
	say( 'Checking for local MySQL...' );

	$candidates = [
		[ 'host' => 'localhost', 'port' => null,  'socket' => '/tmp/mysql.sock' ],
		[ 'host' => 'localhost', 'port' => null,  'socket' => '/opt/homebrew/var/mysql/mysql.sock' ],
		[ 'host' => 'localhost', 'port' => null,  'socket' => '/var/run/mysqld/mysqld.sock' ],
		[ 'host' => '127.0.0.1', 'port' => '3306', 'socket' => null ],
	];

	$creds = [ [ 'root', '' ], [ 'root', 'root' ], [ 'root', 'password' ] ];

	foreach ( $candidates as $c ) {
		foreach ( $creds as [ $u, $p ] ) {
			try {
				$port = (int) ( $c['port'] ?? 3306 );
				$mysqli = new mysqli( $c['host'], $u, $p, '', $port );
				if ( $mysqli->connect_errno === 0 ) {
					$mysqli->close();
					say( "Found local MySQL at {$c['host']}:{$port} (user: {$u})" );
					return [ 'host' => $c['host'], 'port' => (string) $port, 'user' => $u, 'pass' => $p ];
				}
			} catch ( \Throwable $e ) {
				// Try next.
			}
		}
	}

	say( 'No local MySQL found.', ' ' );
	return null;
}

/**
 * Spin up or reattach to a Docker MySQL container.
 */
function start_docker_mysql( string $port ): ?array {
	say( 'Checking for Docker...' );

	if ( ! has_cmd( 'docker' ) ) {
		say( 'Docker not found.', ' ' );
		return null;
	}

	$container = 'fair-test-mysql';

	// Already running?
	$running = trim( (string) shell_exec( "docker ps -q -f name={$container} 2>/dev/null" ) );
	if ( $running !== '' ) {
		say( "Docker MySQL container '{$container}' already running." );
		return [ 'host' => '127.0.0.1', 'port' => $port, 'user' => 'root', 'pass' => 'fair_test' ];
	}

	// Exists but stopped?
	$exists = trim( (string) shell_exec( "docker ps -aq -f name={$container} 2>/dev/null" ) );
	if ( $exists !== '' ) {
		say( 'Starting existing Docker MySQL container...' );
		shell_exec( "docker start {$container} 2>/dev/null" );
	} else {
		say( "Creating Docker MySQL container on port {$port}..." );
		shell_exec( "docker run -d --name {$container} -e MYSQL_ROOT_PASSWORD=fair_test -p {$port}:3306 mysql:8.0 2>&1" );
	}

	// Wait up to 30s.
	say( "Waiting for MySQL on port {$port}..." );
	for ( $i = 0; $i < 30; $i++ ) {
		sleep( 1 );
		if ( mysql_is_reachable( '127.0.0.1', 'root', 'fair_test', $port ) ) {
			say( 'Docker MySQL is ready.' );
			return [ 'host' => '127.0.0.1', 'port' => $port, 'user' => 'root', 'pass' => 'fair_test' ];
		}
		echo '.';
	}

	say( 'Docker MySQL failed to start in time.', '✗' );
	return null;
}

/**
 * Ensure test database exists (creates if needed).
 */
function ensure_database( string $host, string $user, string $pass, string $db_name, string $port ): void {
	try {
		$mysqli = new mysqli( $host, $user, $pass, '', (int) $port );
		if ( $mysqli->connect_errno !== 0 ) {
			bail( "Cannot connect to MySQL: {$mysqli->connect_error}" );
		}

		$result = $mysqli->query( "SHOW DATABASES LIKE '{$db_name}'" );
		if ( $result && $result->num_rows > 0 ) {
			say( "Database '{$db_name}' already exists." );
		} else {
			say( "Creating database '{$db_name}'..." );
			$mysqli->query( "CREATE DATABASE `{$db_name}`" );
			say( "Database '{$db_name}' created." );
		}

		$mysqli->close();
	} catch ( \Throwable $e ) {
		bail( "Database setup failed: {$e->getMessage()}" );
	}
}

/**
 * Generate wp-tests-config.php at project root.
 */
function generate_test_config(
	string $host, string $user, string $pass, string $db_name,
	string $port, string $abspath, string $config_path
): void {
	if ( file_exists( $config_path ) ) {
		say( "wp-tests-config.php already exists — keeping it." );
		return;
	}

	$host_spec = ( $port && $port !== '3306' ) ? "{$host}:{$port}" : $host;

	$content = <<<PHP
<?php
/**
 * WordPress test configuration — auto-generated by bin/setup-local-tests.php
 * DO NOT commit. This file is in .gitignore.
 */

/* Path to WordPress core */
define( 'ABSPATH', '{$abspath}/' );

/* MySQL settings */
define( 'DB_NAME',       '{$db_name}' );
define( 'DB_USER',       '{$user}' );
define( 'DB_PASSWORD',   '{$pass}' );
define( 'DB_HOST',       '{$host_spec}' );
define( 'DB_CHARSET',    'utf8' );
define( 'DB_COLLATE',    '' );

\$table_prefix = 'fairtests_';

define( 'WP_TESTS_DOMAIN',  'example.org' );
define( 'WP_TESTS_EMAIL',   'admin@example.org' );
define( 'WP_TESTS_TITLE',   'Test Blog' );
define( 'WP_PHP_BINARY',    'php' );
define( 'WPLANG',           '' );

PHP;

	file_put_contents( $config_path, $content );
	chmod( $config_path, 0644 );
	say( "Generated {$config_path}" );
}

/**
 * Download WordPress core .tar.gz and extract to $WP_CORE_DIR.
 */
function install_wordpress_core( string $core_dir, string $tmpdir ): void {
	if ( is_dir( $core_dir ) && file_exists( "{$core_dir}/wp-load.php" ) ) {
		say( 'WordPress core already installed.' );
		return;
	}

	$archive = "{$tmpdir}/wordpress-latest.tar.gz";
	$url     = 'https://wordpress.org/latest.tar.gz';

	if ( ! download( $url, $archive, 'WordPress core' ) ) {
		bail( 'Failed to download WordPress core.' );
	}

	say( 'Extracting WordPress core...' );

	if ( ! is_dir( $core_dir ) ) {
		mkdir( $core_dir, 0755, true );
	}

	if ( ! extract_tar_gz( $archive, $core_dir ) ) {
		bail( 'Failed to extract WordPress core. Ensure tar is installed.' );
	}

	unlink( $archive );
	say( 'WordPress core installed.' );
}

/**
 * Download the WordPress PHPUnit test suite.
 *
 * Tries the configured version tag, then queries GitHub API for the latest
 * matching tag, then falls back to trunk.
 */
function install_test_suite( string $tests_dir, string $wp_version, string $tmpdir ): void {
	if ( file_exists( "{$tests_dir}/includes/functions.php" ) ) {
		say( 'Test suite already installed.' );
		return;
	}

	$urls_to_try = [];

	// 1. Specific version tag (e.g. 6.2.0).
	$urls_to_try[] = [
		"https://github.com/WordPress/wordpress-develop/archive/refs/tags/{$wp_version}.tar.gz",
		$wp_version,
	];

	// 2. Try to find the latest matching tag via GitHub API.
	$major_minor = implode( '.', array_slice( explode( '.', $wp_version ), 0, 2 ) );
	$api_url      = "https://api.github.com/repos/WordPress/wordpress-develop/tags?per_page=30";
	$api_response = @file_get_contents( $api_url );
	if ( $api_response !== false ) {
		$tags = json_decode( $api_response, true );
		if ( is_array( $tags ) ) {
			foreach ( $tags as $tag ) {
				$name = $tag['name'] ?? '';
				if ( str_starts_with( $name, $major_minor . '.' ) ) {
					$urls_to_try[] = [
						"https://github.com/WordPress/wordpress-develop/archive/refs/tags/{$name}.tar.gz",
						$name,
					];
					break;
				}
			}
		}
	}

	// 3. Trunk fallback.
	$urls_to_try[] = [
		'https://github.com/WordPress/wordpress-develop/archive/refs/heads/trunk.tar.gz',
		'trunk',
	];

	$success = false;
	foreach ( $urls_to_try as [ $url, $label ] ) {
		$archive = "{$tmpdir}/wp-tests-{$label}.tar.gz";

		if ( download( $url, $archive, "WP test suite ({$label})" ) ) {
			$success = true;
			break;
		}
		// Clean up failed download.
		@unlink( $archive );
		say( "Tag '{$label}' not found, trying next...", ' ' );
	}

	if ( ! $success || ! isset( $archive ) ) {
		bail( 'Failed to download test suite from GitHub. Check network connection.' );
	}

	say( 'Extracting test suite...' );

	// Extract to a temp directory first, then move the files we need.
	$extract_dir = "{$tmpdir}/wp-test-extract";
	if ( is_dir( $extract_dir ) ) {
		// Clean up previous extraction.
		$iter = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $extract_dir, RecursiveDirectoryIterator::SKIP_DOTS ),
			RecursiveIteratorIterator::CHILD_FIRST
		);
		foreach ( $iter as $f ) {
			$f->isDir() ? rmdir( $f->getPathname() ) : unlink( $f->getPathname() );
		}
	} else {
		mkdir( $extract_dir, 0755, true );
	}

	if ( ! extract_tar_gz( $archive, $extract_dir ) ) {
		bail( 'Failed to extract test suite archive.' );
	}

	// The extracted content will be in a subdirectory like:
	// wordpress-develop-6.2/tests/phpunit/includes/
	// wordpress-develop-6.2/tests/phpunit/data/
	$subdir = glob( "{$extract_dir}/wordpress-develop-*", GLOB_ONLYDIR );
	if ( empty( $subdir ) ) {
		// Try trunk naming.
		$subdir = glob( "{$extract_dir}/wordpress-develop-trunk*", GLOB_ONLYDIR );
	}
	if ( empty( $subdir ) ) {
		// Maybe extracted without subdirectory.
		$subdir = [ $extract_dir ];
	}
	$base = $subdir[0];

	$src_includes = "{$base}/tests/phpunit/includes";
	$src_data     = "{$base}/tests/phpunit/data";

	if ( ! is_dir( $src_includes ) || ! is_dir( $src_data ) ) {
		bail( 'Test suite archive does not contain expected directories. WordPress develop structure may have changed.' );
	}

	// Move includes and data to the tests dir.
	if ( ! is_dir( $tests_dir ) ) {
		mkdir( $tests_dir, 0755, true );
	}

	// Use copy + delete instead of rename (cross-filesystem safe).
	recurse_copy( $src_includes, "{$tests_dir}/includes" );
	recurse_copy( $src_data, "{$tests_dir}/data" );

	// Patch: E_STRICT was removed in PHP 8.4. The WP test library's
	// install.php references it in error_reporting(), which causes
	// a "Constant E_STRICT is deprecated" noise on every test run.
	// This must be patched here (not in bootstrap.php) because
	// install.php runs as a separate subprocess via system().
	$install_php = "{$tests_dir}/includes/install.php";
	if ( file_exists( $install_php ) ) {
		$content = file_get_contents( $install_php );
		$content = str_replace(
			'E_ALL & ~E_DEPRECATED & ~E_STRICT',
			'E_ALL & ~E_DEPRECATED',
			$content
		);
		file_put_contents( $install_php, $content );
	}

	// Also copy the bundled wp-tests-config-sample.php for reference.
	if ( file_exists( "{$base}/wp-tests-config-sample.php" ) ) {
		copy( "{$base}/wp-tests-config-sample.php", "{$tests_dir}/wp-tests-config-sample.php" );
	}

	// Cleanup.
	unlink( $archive );
	$iter = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator( $extract_dir, RecursiveDirectoryIterator::SKIP_DOTS ),
		RecursiveIteratorIterator::CHILD_FIRST
	);
	foreach ( $iter as $f ) {
		$f->isDir() ? rmdir( $f->getPathname() ) : unlink( $f->getPathname() );
	}
	rmdir( $extract_dir );

	say( 'Test suite installed.' );
}

/**
 * Install the db.php drop-in for MySQLi compatibility.
 */
function install_db_dropin( string $core_dir ): void {
	$dest = "{$core_dir}/wp-content/db.php";
	if ( file_exists( $dest ) ) {
		return;
	}

	$content_dir = "{$core_dir}/wp-content";
	if ( ! is_dir( $content_dir ) ) {
		mkdir( $content_dir, 0755, true );
	}

	$url = 'https://raw.githubusercontent.com/markoheijnen/wp-mysqli/master/db.php';
	say( 'Downloading mysqli drop-in...' );
	if ( ! download( $url, $dest, 'mysqli db.php drop-in' ) ) {
		say( 'Warning: Could not download db.php drop-in. Tests may still work.', '!' );
	}
}

// ── Main ───────────────────────────────────────────────────────────

say( 'FAIR Plugin — local test environment setup' );
say( str_repeat( '─', 50 ) );

// 1. Fast path: already installed and MySQL reachable.
$config_path = dirname( __DIR__ ) . '/wp-tests-config.php';
if ( file_exists( "{$WP_TESTS_DIR}/includes/functions.php" ) && file_exists( $config_path ) ) {
	say( "Test suite already installed at {$WP_TESTS_DIR}" );

	// Check MySQL still reachable.
	$creds_to_try = [
		[ 'host' => $DB_HOST, 'user' => $DB_USER, 'pass' => $DB_PASS, 'port' => $DB_PORT ],
	];

	// Also try creds from existing config file.
	$conf_lines = @file( $config_path );
	if ( $conf_lines ) {
		$cu = $DB_USER; $cp = $DB_PASS;
		foreach ( $conf_lines as $line ) {
			if ( preg_match( "/define\(\s*'DB_USER',\s*'([^']+)'/", $line, $m ) ) {
				$cu = $m[1];
			}
			if ( preg_match( "/define\(\s*'DB_PASSWORD',\s*'([^']*)'/", $line, $m ) ) {
				$cp = $m[1];
			}
		}
		$creds_to_try[] = [ 'host' => $DB_HOST, 'user' => $cu, 'pass' => $cp, 'port' => $DB_PORT ];
	}

	$mysql_ok = false;
	foreach ( $creds_to_try as $c ) {
		if ( mysql_is_reachable( $c['host'], $c['user'], $c['pass'], $c['port'] ) ) {
			$mysql_ok = true;
			break;
		}
	}

	if ( $mysql_ok ) {
		say( 'MySQL is reachable. Ready to test.' );
		exit( 0 );
	}

	say( 'MySQL not reachable — re-detecting...', ' ' );
}

// 2. Check prerequisites.
say( 'Checking prerequisites...' );
if ( ! has_cmd( 'php' ) ) {
	bail( 'PHP is required and must be in PATH.' );
}
if ( ! has_cmd( 'curl' ) && ! has_cmd( 'wget' ) && ! ini_get( 'allow_url_fopen' ) ) {
	bail( 'curl, wget, or allow_url_fopen is required for downloads.' );
}
say( 'Prerequisites OK.' );

// 3. Detect/create MySQL.
$mysql = null;

// Try the explicitly configured host first (env vars).
if ( getenv( 'FAIR_TEST_DB_HOST' ) ) {
	$host = getenv( 'FAIR_TEST_DB_HOST' );
	$port = getenv( 'FAIR_TEST_DB_PORT' ) ?: '3306';
	$user = $DB_USER;
	$pass = $DB_PASS;

	say( "Trying configured MySQL at {$host}:{$port}..." );
	try {
		$mysqli = new mysqli( $host, $user, $pass, 'fair_test', (int) $port );
		if ( $mysqli->connect_errno === 0 ) {
			$mysqli->close();
			say( "Connected to configured MySQL at {$host}:{$port}" );
			$mysql = [ 'host' => $host, 'port' => $port, 'user' => $user, 'pass' => $pass ];
		}
	} catch ( \Throwable $e ) {
		say( "Configured MySQL not reachable: {$e->getMessage()}" );
	}
}

if ( ! $mysql ) {
	$mysql = find_local_mysql();
}
if ( ! $mysql ) {
	$mysql = start_docker_mysql( $DOCKER_MYSQL_PORT );
}
if ( ! $mysql ) {
	bail( <<<HELP
No MySQL instance found.

Options:
  1. Start local MySQL:  brew services start mysql
  2. Or use Docker:      docker run -d --name fair-test-mysql \\
                            -e MYSQL_ROOT_PASSWORD=fair_test -p 3309:3306 mysql:8.0
  3. Or set env vars:    export FAIR_TEST_DB_HOST=... FAIR_TEST_DB_USER=... FAIR_TEST_DB_PASS=...

Then re-run: composer run test:unit
HELP
	);
}

$DB_HOST = $mysql['host'];
$DB_PORT = $mysql['port'];
$DB_USER = $mysql['user'];
$DB_PASS = $mysql['pass'];

// 4. Create database.
ensure_database( $DB_HOST, $DB_USER, $DB_PASS, $DB_NAME, $DB_PORT );

// 5. Generate wp-tests-config.php.
generate_test_config( $DB_HOST, $DB_USER, $DB_PASS, $DB_NAME, $DB_PORT, $WP_CORE_DIR, $config_path );

// 6. Download WordPress core.
install_wordpress_core( $WP_CORE_DIR, $TMPDIR );

// 7. Download test suite.
install_test_suite( $WP_TESTS_DIR, $WP_VERSION, $TMPDIR );

// 8. Install db.php drop-in.
install_db_dropin( $WP_CORE_DIR );

// 9. Verify.
if ( ! file_exists( "{$WP_TESTS_DIR}/includes/functions.php" ) ) {
	bail( "Test suite not found at {$WP_TESTS_DIR}/includes/functions.php after install." );
}

say( str_repeat( '─', 50 ) );
say( 'Setup complete. Run: composer run test:unit' );
exit( 0 );
