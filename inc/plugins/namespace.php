<?php
/**
 * Sets DID-less plugin slug as active.
 *
 * @package FAIR
 */

namespace FAIR\Plugins;

use function FAIR\Packages\get_did_hash;
use function FAIR\Updater\get_packages;

/**
 * Bootstrap.
 *
 * @return void
 */
function bootstrap() {
	add_action( 'load-plugins.php', __NAMESPACE__ . '\\load_filters' );
}

/**
 * Load filters.
 *
 * @return void
 */
function load_filters() {
	add_filter( 'option_active_plugins', __NAMESPACE__ . '\\set_as_active' );
	add_filter( 'wp_admin_notice_markup', __NAMESPACE__ . '\\hide_notice', 10, 3 );

	// just for testing.
	if ( ! function_exists( 'is_plugin_active' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}
	if ( is_plugin_active( 'git-updater/git-updater.php' ) ) {
		wp_admin_notice( 'Git Updater is active' );
	}
}

/**
 * Set FAIR plugins as active using DID-less slug.
 *
 * @param  array $active_plugins Array of active plugins.
 *
 * @return array
 */
function set_as_active( $active_plugins ) {
	remove_filter( 'option_active_plugins', __NAMESPACE__ . '\\set_as_active' );
	$packages = get_packages();
	$plugins = $packages['plugins'] ?? [];
	$plugins = array_map( 'plugin_basename', $plugins );
	foreach ( $plugins as $did => $plugin ) {
		if ( is_plugin_active( $plugin ) ) {
			$active_plugins[] = get_file_without_did_hash( $did, $plugin );
		}
	}

	return array_filter( array_unique( $active_plugins ) );
}

/**
 * Return plugin file without DID hash.
 *
 * Assumes pattern of <slug>-<hash>.
 *
 * @param string $did DID.
 * @param string $plugin Plugin basename.
 *
 * @return string
 */
function get_file_without_did_hash( $did, $plugin ) : string {
	list( $slug, $file ) = explode( '/', $plugin, 2 );
	$slug = str_replace( '-' . get_did_hash( $did ), '', $slug );

	return $slug . '/' . $file;
}

/**
 * Hide notice reporting DID-less plugin is inactive because it doesn't exist.
 *
 * @param  string $markup Markup of notice.
 * @param  string $message Message of notice.
 * @param  array $args Args of notice.
 *
 * @return string
 */
function hide_notice( $markup, $message, $args ) {
	if ( $args['id'] === 'message' ) {
		$active = get_option( 'active_plugins' );
		foreach ( $active as $plugin ) {
			if ( str_contains( $message, $plugin ) && str_contains( $markup, 'error' ) ) {
				remove_filter( 'wp_admin_notice_markup', __NAMESPACE__ . '\\hide_notice' );
				return '';
			}
		}
	}

	return $markup;
}
