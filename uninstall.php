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

// Delete our single keys.
delete_option( 'fair_indexnow_key' );

// Our multisite level options.
delete_site_option( 'fair_avatar_source' );
