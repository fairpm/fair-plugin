<?php
/**
 * Integration test seed script.
 *
 * Run via: wp eval-file tests/sites/integration/seed.php
 *
 * Pre-populates the environment for integration tests.
 *
 * @package FAIR
 */

// The plugin's composer autoloader must be loaded for plugin classes.
require_once '/var/www/html/wp-content/plugins/fair-plugin/vendor/autoload.php';

// Reload the plugin if needed (it should already be active).
if ( ! function_exists( 'FAIR\\bootstrap' ) ) {
	require_once '/var/www/html/wp-content/plugins/fair-plugin/plugin.php';
}

// Register a test plugin so the updater picks it up.
$test_plugin_dir  = WP_PLUGIN_DIR . '/fair-test-plugin';
$test_plugin_file = $test_plugin_dir . '/fair-test-plugin.php';

if ( ! is_dir( $test_plugin_dir ) ) {
	mkdir( $test_plugin_dir, 0755, true );
}

// Create a minimal plugin file with FAIR headers.
file_put_contents( $test_plugin_file, <<<PHP
<?php
/**
 * Plugin Name: FAIR Test Plugin
 * Plugin ID: did:plc:z72i7hdynmk6r22z27h6tvur
 * Version: 1.0.0
 * Description: Integration test plugin for FAIR Connect.
 */
PHP
);

// Register with the updater.
\FAIR\Updater\Updater::register_plugin(
	'did:plc:z72i7hdynmk6r22z27h6tvur',
	$test_plugin_file
);

echo "Seed complete: registered test plugin\n";

// Register a second plugin with a DID the mock server doesn't recognize.
// This exercises the error propagation path: fetch fails → error cached →
// plugin skipped in transient → error row displayed.
$bad_plugin_dir  = WP_PLUGIN_DIR . '/fair-bad-plugin';
$bad_plugin_file = $bad_plugin_dir . '/fair-bad-plugin.php';

if ( ! is_dir( $bad_plugin_dir ) ) {
	mkdir( $bad_plugin_dir, 0755, true );
}

file_put_contents( $bad_plugin_file, <<<PHP
<?php
/**
 * Plugin Name: FAIR Bad Plugin
 * Plugin ID: did:plc:doesnotexist0000000000000
 * Version: 1.0.0
 * Description: Plugin with unresolvable DID for error propagation testing.
 */
PHP
);

\FAIR\Updater\Updater::register_plugin(
	'did:plc:doesnotexist0000000000000',
	$bad_plugin_file
);

echo "Seed complete: registered unresolvable-DID plugin for error testing\n";
