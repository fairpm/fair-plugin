<?php
/**
 * Sets DID-less plugin slug as active.
 *
 * @package FAIR
 */

namespace FAIR\Plugins;

use function FAIR\Updater\get_packages;

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
	$packages = get_packages();
	$plugins = $packages['plugins'] ?? [];
	foreach ( $plugins as $plugin ) {
		if ( is_plugin_active( plugin_basename( $plugin ) ) ) {
			$plugins[] = get_didless_slug( $plugin );
		}
	}
	$active_plugins = array_map( 'plugin_basename', array_merge( $active_plugins, $plugins ) );

	return array_unique( $active_plugins );
}

/**
 * Return shortened DID withou `did:plc|web'.
 *
 * @param  string $did Full DID.
 *
 * @return string|void
 */
function get_short_did( $did ) {
	if ( ! empty( $did ) ) {
		if ( str_contains( $did, ':' ) ) {
			$did = (string) explode( ':', $did )[2];
		}
		return $did;
	}
}

/**
 * Return slug without DID.
 *
 * @param  string $slug Current slug.
 * @param  string $did  Full DID.
 *
 * @return string|void
 */
function get_didless_slug( $slug, $did = '' ) {
	if ( empty( $did ) ) {
		$did = get_file_data( $slug, [ 'PluginID' => 'Plugin ID' ] )['PluginID'];
		$did = get_short_did( $did );
	}
	if ( ! empty( $did ) ) {
		$slug = str_replace( '-' . get_short_did( $did ), '', $slug );
	}

	return $slug;
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
