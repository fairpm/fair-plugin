<?php
/**
 * Our uninstall call.
 *
 * @package FAIR
 */

// Only run this on the actual WP uninstall function.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	die;
}

// Include the avatar namespace so we can use the constant.
require_once __DIR__ . '/inc/avatars/namespace.php';

// Delete our single keys.
delete_option( 'fair_indexnow_key' );

// Our multisite level options.
delete_site_option( \FAIR\Avatars\AVATAR_SRC_SETTING_KEY );

// Any transients we may have.
delete_site_transient( 'update_plugins' );
delete_site_transient( 'update_themes' );
