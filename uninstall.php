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

// Delete any single or multisite keys.
delete_option( 'fair_indexnow_key' );
delete_site_option( \FAIR\Avatars\AVATAR_SRC_SETTING_KEY );

// Delete any transients we may have created or modified.
delete_site_transient( 'update_plugins' );
delete_site_transient( 'update_themes' );

global $wpdb;

// Prepare our delete query.
$query_args = $wpdb->prepare("
	DELETE FROM $wpdb->usermeta
	WHERE       meta_key IN (
		%s,
		%s
	)
", 'fair_avatar_site_id', 'fair_avatar_id' );

// And actually run it.
// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- we did use the prepare function above
$wpdb->query( $query_args );
