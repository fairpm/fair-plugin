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
