<?php
/**
 * Update FAIR packages.
 *
 * @package FAIR
 */

namespace FAIR\Updater;

/**
 * Bootstrap.
 */
function bootstrap() {
	add_action( 'init', __NAMESPACE__ . '\\run' );
}

/**
 * Gather all plugins/themes with data in Update URI and DID header.
 *
 * @return array
 */
function get_packages() : array {
	$packages = [];

	// Seems to be required for PHPUnit testing on GitHub workflow.
	if ( ! function_exists( 'get_plugins' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}

	$plugin_path = trailingslashit( WP_PLUGIN_DIR );
	$plugins     = get_plugins();
	foreach ( $plugins as $file => $plugin ) {
		$plugin_id = get_file_data( $plugin_path . $file, [ 'PluginID' => 'Plugin ID' ] )['PluginID'];
		if ( ! empty( $plugin_id ) ) {
			$packages['plugins'][ $plugin_id ] = $plugin_path . $file;
		}
	}

	$theme_path = WP_CONTENT_DIR . '/themes/';
	$themes     = wp_get_themes();
	foreach ( $themes as $file => $theme ) {
		$theme_id = get_file_data( $theme_path . $file . '/style.css', [ 'ThemeID' => 'Theme ID' ] )['ThemeID'];
		if ( ! empty( $theme_id ) ) {
			$packages['themes'][ $theme_id ] = $theme_path . $file . '/style.css';
		}
	}

	return $packages;
}

/**
 * Run FAIR\Updater\Updater for potential packages.
 *
 * @return void
 */
function run() {
	$packages = get_packages();
	$plugins = $packages['plugins'] ?? [];
	$themes = $packages['themes'] ?? [];
	$packages = array_merge( $plugins, $themes );
	foreach ( $packages as $did => $filepath ) {
		( new Updater( $did, $filepath ) )->run();
	}
}
