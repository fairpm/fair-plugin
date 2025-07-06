<?php
/**
 * Sets DID-less plugin slug as active.
 *
 * @package FAIR
 */

namespace FAIR\Plugins;

use function FAIR\Updater\get_packages;

use WP_Error;

/**
 * Bootstrap
 *
 * @return void
 */
function bootstrap() {
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
	$active_without_did_id = [];
	$packages = get_packages();
	$plugins = $packages['plugins'] ?? [];
	foreach ( $plugins as $plugin ) {
		if ( is_plugin_active( plugin_basename( $plugin ) ) ) {
			$active_without_did_id[] = get_slug_without_did_id( $plugin );
		}
	}
	$active_plugins = array_map( 'plugin_basename', array_merge( $active_plugins, $active_without_did_id ) );

	return array_unique( $active_plugins );
}

/**
 * Return DID parts.
 *
 * @param  string $id Full DID.
 *
 * @return string|WP_Error
 */
function get_did_parts( $id ) {
	$parts = explode( ':', $id, 3 );
	if ( count( $parts ) !== 3 ) {
		return new WP_Error( 'fair.packages.validate_did.not_uri', __( 'DID could not be parsed as a URI.', 'fair' ) );
	}

	return [
		'method' => $parts[1],
		'id' => $parts[2],
	];
}

/**
 * Return plugin file path without DID.
 *
 * @param  string $plugin Filepath or plugin basename.
 * @param  string $did  Full DID.
 *
 * @return string
 */
function get_slug_without_did_id( $plugin, $did = '' ) {
	if ( empty( $did ) ) {
		$plugin = trailingslashit( WP_PLUGIN_DIR ) . plugin_basename( $plugin );
		$did = get_file_data( $plugin, [ 'PluginID' => 'Plugin ID' ] )['PluginID'];
	}
	$did = get_did_parts( $did );
	if ( is_wp_error( $did ) ) {
		return $plugin;
	}
	$plugin = str_replace( '-' . $did['id'], '', $plugin );

	return $plugin;
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
