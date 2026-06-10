<?php
/**
 * Seed browser test environment.
 *
 * Creates admin user with known credentials for Playwright login.
 * Also activates the plugin and registers a test package.
 */

require_once '/var/www/html/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/user.php';

// Create browser_test admin with known password.
$admin_id = username_exists( 'browser_admin' );
if ( ! $admin_id ) {
	$admin_id = wp_create_user( 'browser_admin', 'browser_test_password', 'browser_admin@example.com' );
	if ( is_wp_error( $admin_id ) ) {
		echo 'Error: ' . $admin_id->get_error_message() . "\n";
		exit( 1 );
	}
	$user = new WP_User( $admin_id );
	$user->set_role( 'administrator' );
}

echo "Seed complete: browser_admin user ready\n";

// Create a dummy plugin with a FAIR DID header so register_plugin_row_hooks()
// finds it and display_plugin_update_error() fires on the plugins page.
$test_did = 'did:plc:hellodolly000000000000001';
$plugin_dir = WP_PLUGIN_DIR . '/fair-error-demo';
$plugin_file = $plugin_dir . '/fair-error-demo.php';

if ( ! file_exists( $plugin_dir ) ) {
	mkdir( $plugin_dir, 0755, true );
}

file_put_contents( $plugin_file, "<?php\n/**\n * Plugin Name: FAIR Error Demo\n * Plugin ID: {$test_did}\n * Version: 1.0.0\n */" );

// Seed a fake update error transient so the update-error-row browser test
// can verify error rows render correctly.
$error = new WP_Error(
	'fair.packages.did.fetch_error',
	'Could not fetch release information for this package.'
);
$error->add_data( [ 'timestamp' => time() - 60 ], 'fair.packages.did.fetch_error' );
set_site_transient( 'fair_update-errors-' . $test_did, $error, HOUR_IN_SECONDS );

echo "Seed complete: dummy plugin and error transient set for {$test_did}\n";
