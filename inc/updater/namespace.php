<?php

namespace FAIR\Updater;

use Fragen\Git_Updater;

/**
 * Exit if called directly.
 */
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Bootstrap.
 */
function bootstrap() {
	add_action( 'init', __NAMESPACE__ . '\\run' );
}

/**
 * Gather all plugins/themes with data in Update URI and DID header.
 *
 * @return stdClass
 */
function get_packages() {
	/** @var array */
	$packages = [];

	// Seems to be required for PHPUnit testing on GitHub workflow.
	if ( ! function_exists( 'get_plugins' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}

	$plugin_path = trailingslashit( WP_PLUGIN_DIR );
	$plugins     = get_plugins();
	foreach ( $plugins as $file => $plugin ) {
		if ( empty( $plugin['UpdateURI'] ) ) {
			continue;
		}
		$plugin_id = get_file_data( $plugin_path . $file, [ 'PluginID' => 'Plugin ID' ] )['PluginID'];

		if ( ! empty( $plugin_id ) ) {
			$packages['plugins'][] = $plugin_path . $file;
		}
	}

	$theme_path = WP_CONTENT_DIR . '/themes/';
	$themes     = wp_get_themes();
	foreach ( $themes as $file => $theme ) {
		if ( empty( $theme->get( 'UpdateURI' ) ) ) {
			continue;
		}
		$theme_id = get_file_data( $theme_path . $file . '/style.css', [ 'ThemeID' => 'Theme ID' ] )['ThemeID'];

		if ( ! empty( $theme_id ) ) {
			$packages['themes'][] = $theme_path . $file . '/style.css';
		}
	}

	return $packages;
}

/**
 * Run Git Updater Lite for potential packages.
 *
 * @return void
 */
function run() {
	$packages = get_packages();
	$plugins = $packages['plugins'] ?? [];
	$themes = $packages['themes'] ?? [];
	$packages = array_merge( $plugins, $themes);
	foreach ( $packages as $package ) {
		( new Git_Updater\Lite( $package ) )->run();
	}
}
