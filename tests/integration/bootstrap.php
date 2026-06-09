<?php
/**
 * Integration test bootstrap — runs inside Docker wp-cli container.
 *
 * Loads WordPress + plugin + composer autoloader directly.
 * Does NOT require the WordPress test suite (WP_UnitTestCase).
 * Tests extend PHPUnit\Framework\TestCase and use real WP APIs.
 *
 * @package FAIR
 */

// Composer autoloader (plugin's vendor directory).
$plugin_dir = '/var/www/html/wp-content/plugins/fair-plugin';
$autoloader = "{$plugin_dir}/vendor/autoload.php";

if ( ! file_exists( $autoloader ) ) {
	fwrite( STDERR, "Composer autoloader not found at {$autoloader}\n" );
	fwrite( STDERR, "Run 'composer install' in the plugin directory first.\n" );
	exit( 1 );
}

require_once $autoloader;

// Load WordPress.
$_tests_dir = '/var/www/html';

if ( ! file_exists( "{$_tests_dir}/wp-load.php" ) ) {
	fwrite( STDERR, "WordPress not found at {$_tests_dir}\n" );
	exit( 1 );
}

// Define constants before WP loads.
define( 'FAIR_PLC_DIRECTORY_URL', 'http://mock-server:8080' );
define( 'FAIR_DEFAULT_REPO_DOMAIN', 'mock-server:8080' );

// Load WP (but don't send headers — we're in CLI mode).
$_SERVER['HTTP_HOST'] = 'integration.local';
define( 'WP_USE_THEMES', false );
require_once "{$_tests_dir}/wp-load.php";

// Load the plugin (already activated, but ensure functions are available).
require_once "{$plugin_dir}/plugin.php";
