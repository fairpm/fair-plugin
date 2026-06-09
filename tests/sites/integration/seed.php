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
